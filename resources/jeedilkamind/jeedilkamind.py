# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import logging
import sys
import os
import time
import traceback
import signal
import json
import argparse
import edilkamin
import jwt
import requests

from jeedom.jeedom import jeedom_socket, jeedom_utils, jeedom_com, JEEDOM_SOCKET_MESSAGE  # jeedom_serial

def validJWT():
    logging.debug("Valid JWT")
    COGNITO_REGION = "eu-central-1"
    USER_POOL_ID = "eu-central-1_BYmQ2VBlo"

    JWKS_URL = f"https://cognito-idp.{COGNITO_REGION}.amazonaws.com/{USER_POOL_ID}/.well-known/jwks.json"

    # 1. Récupérer les clés publiques
    jwks = requests.get(JWKS_URL).json()

    header = jwt.get_unverified_header(_token)
    kid = header["kid"]
    key = next(k for k in jwks["keys"] if k["kid"] == kid)
    public_key = jwt.algorithms.RSAAlgorithm.from_jwk(key)
    try:
        jwt.decode(_token, public_key, audience="7sc1qltkqobo3ddqsk4542dg2h", algorithms=["RS256"])
        logging.info("Token valid")
    except jwt.ExpiredSignatureError:
        logging.info("Token expired")
        login(_email,_password)
    except Exception as e:
        logging.error("ValidJWT: %s",e)

def login(username, password):
    """Login and return token."""
    global _token
    try:
        logging.debug("username : %s", username)
        logging.debug("password : %s", password)
        _token = edilkamin.sign_in(username, password)
        logging.info("Logged in to Edilkamin API")
    except Exception as e:
        logging.error("Login failed: %s",e)
    return None

def device_info(macaddress):
    try:
        logging.debug("macaddress : %s", macaddress)
        logging.debug("token : %s", _token)
        validJWT()
        return json.dumps(edilkamin.device_info(_token, macaddress)).replace('\\', '')
    except Exception as e:      
        logging.error("[device_info]Login failed: %s",e)
    return None

def refresh(info: dict):
    try:
        refresh_infos = {}
        refresh_infos['state'] = edilkamin.device_info_get_power(info).value
        nbFans = info['nvm']['installer_parameters']['fans_number']
        for i in range(nbFans):
            refresh_infos['fan'+str(i+1)] = edilkamin.device_info_get_fan_speed(info, i+1)
        refresh_infos['temperature'] = edilkamin.device_info_get_environment_temperature(info)
        refresh_infos['alarm_type'] = edilkamin.device_info_get_alarm_reset(info)
        refresh_infos['manual_power_level'] = edilkamin.device_info_get_manual_power_level(info)
        refresh_infos['pellet_autonomy_time'] = edilkamin.device_info_get_autonomy_time(info)
        refresh_infos['actual_power'] = info['status']['state']['actual_power']
        refresh_infos['is_auto'] = info['nvm']['user_parameters']['is_auto']
        logging.debug('Phase stove_state : %s', info['status']['state']['stove_state'])
        logging.debug('Phase operational_phase : %s', info['status']['state']['operational_phase'])
        logging.debug('Phase sub_operational_phase : %s', info['status']['state']['sub_operational_phase'])
        return refresh_infos
    except Exception as e:
        logging.error('[Refresh] %s', e)

def read_socket():
    if not JEEDOM_SOCKET_MESSAGE.empty():
        logging.debug("Message received in socket JEEDOM_SOCKET_MESSAGE")
        #message = json.loads(jeedom_utils.stripped(JEEDOM_SOCKET_MESSAGE.get()))
        message = json.loads(JEEDOM_SOCKET_MESSAGE.get())
        if message['apikey'] != _apikey:
            logging.error("Invalid apikey from socket: %s", message)
            return
        try:
            logging.debug("Init read_socket")
            if (not message['macaddress']):
                raise Exception("Mac Address is empty!")
            
            forJeedom = {}
            
            logging.debug(message['action'])
            if (message['action'] == 'postSave'):
                if (message['eqlogicid']):
                    forJeedom['eqlogicid'] = message['eqlogicid']
                if (message['countcmd']):
                    forJeedom['countcmd'] = message['countcmd']
            elif (message['action'] == 'set_power_on'):
                logging.debug(edilkamin.set_power_on(_token, message['macaddress']))
                #loop_on_power(message['macaddress'], forJeedom)
                while 1:
                    info = device_info(message['macaddress'])
                    forJeedom['infos'] = info
                    forJeedom['refresh_infos'] = refresh(json.loads(info))
                    my_jeedom_com.send_change_immediate(forJeedom)
                    if (info['status']['state']['stove_state'] == 6 and info['status']['state']['operational_phase'] == 2 and info['status']['state']['sub_operational_phase'] == 2):
                        break
                    time.sleep(30.0)
            elif (message['action'] == 'set_power_off'):
                logging.debug(edilkamin.set_power_off(_token, message['macaddress']))
                while 1:
                    info = device_info(message['macaddress'])
                    forJeedom['infos'] = info
                    forJeedom['refresh_infos'] = refresh(json.loads(info))
                    my_jeedom_com.send_change_immediate(forJeedom)
                    if (info['status']['state']['stove_state'] == 1 and info['status']['state']['operational_phase'] == 0 and info['status']['state']['sub_operational_phase'] == 0):
                        break
                    time.sleep(30.0)
            elif (message['action'].startswith('fan_speed')):
                fanId = int(message['action'][-1])
                logging.debug(edilkamin.set_fan_speed(_token, message['macaddress'], fanId, int(message['speed'])))
            elif (message['action'].startswith('manual_power')):
                logging.debug(edilkamin.set_manual_power_level(_token, message['macaddress'], int(message['manual_power'])))
            elif (message['action'] == 'set_auto_on'):
                payload = {"name": "auto_mode", "value": True}
                logging.debug(edilkamin.mqtt_command(_token, message['macaddress'], payload))
            elif (message['action'] == 'set_auto_off'):
                payload = {"name": "auto_mode", "value": False}
                logging.debug(edilkamin.mqtt_command(_token, message['macaddress'], payload))
            elif (message['action'] == 'set_relax_on'):
                logging.debug(edilkamin.set_relax_mode(_token, message['macaddress'], True))
            elif (message['action'] == 'set_relax_off'):
                logging.debug(edilkamin.set_relax_mode(_token, message['macaddress'], False))

            time.sleep(1.0)
            info = device_info(message['macaddress'])
            forJeedom['infos'] = info
            forJeedom['refresh_infos'] = refresh(json.loads(info))
            my_jeedom_com.send_change_immediate(forJeedom)
        except Exception as e:
            logging.error('Send command to demon error: %s', e)


def listen():
    my_jeedom_socket.open()
    try:
        while 1:
            time.sleep(0.5)
            read_socket()
    except KeyboardInterrupt:
        shutdown()


def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting...", int(signum))
    shutdown()


def shutdown():
    logging.debug("Shutdown")
    logging.debug("Removing PID file %s", _pidfile)
    try:
        os.remove(_pidfile)
    except Exception as e:
        logging.warning('Error removing PID file: %s', e)
    try:
        my_jeedom_socket.close()
    except Exception as e:
        logging.warning('Error closing socket: %s', e)
    # try:  # if you need jeedom_serial
    #     my_jeedom_serial.close()
    # except Exception as e:
    #     logging.warning('Error closing serial: %s', e)
    logging.debug("Exit 0")
    sys.stdout.flush()
    os._exit(0)


_log_level = "error"
_socket_port = 51981
_socket_host = 'localhost'
_device = 'auto'
_pidfile = '/tmp/demond.pid'
_apikey = ''
_callback = ''
_cycle = 0.3

parser = argparse.ArgumentParser(description='Desmond Daemon for Jeedom plugin')
parser.add_argument("--device", help="Device", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Callback", type=str)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=float)
parser.add_argument("--pid", help="Pid file", type=str)
parser.add_argument("--socketport", help="Port for socket server", type=int)
parser.add_argument("--email", help="Email address", type=str)
parser.add_argument("--password", help="Password", type=str)
args = parser.parse_args()

if args.device:
    _device = args.device
if args.loglevel:
    _log_level = args.loglevel
if args.callback:
    _callback = args.callback
if args.apikey:
    _apikey = args.apikey
if args.pid:
    _pidfile = args.pid
if args.cycle:
    _cycle = float(args.cycle)
if args.socketport:
    _socket_port = args.socketport
if args.email:
    _email = args.email
if args.password:
    _password = args.password

_socket_port = int(_socket_port)

jeedom_utils.set_log_level(_log_level)

logging.info('Start demond')
logging.info('Log level: %s', _log_level)
logging.info('Socket port: %s', _socket_port)
logging.info('Socket host: %s', _socket_host)
logging.info('PID file: %s', _pidfile)
logging.info('Apikey: %s', _apikey)
logging.info('Device: %s', _device)
logging.info('Email: %s', _email)
logging.debug('Password: %s', _password) 

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)

try:
    jeedom_utils.write_pid(str(_pidfile))
    my_jeedom_com = jeedom_com(apikey=_apikey, url=_callback, cycle=_cycle)
    if not my_jeedom_com.test():
        logging.error('Network communication issues. Please fixe your Jeedom network configuration.')
        shutdown()
    # my_jeedom_serial = jeedom_serial(device=_device)  # if you need jeedom_serial
    my_jeedom_socket = jeedom_socket(port=_socket_port, address=_socket_host)
    login(_email,_password)
    listen()
except Exception as e:
    logging.error('Fatal error: %s', e)
    logging.info(traceback.format_exc())
    shutdown()
