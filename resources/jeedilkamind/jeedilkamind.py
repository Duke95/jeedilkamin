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
import asyncio
import inspect
import traceback
import signal
import json
import argparse
from datetime import datetime
import edilkamin
import jwt
import aiohttp

from jeedom.jeedom import jeedom_socket, jeedom_utils, jeedom_com, JEEDOM_SOCKET_MESSAGE  # jeedom_serial

_COGNITO_REGION = "eu-central-1"
_COGNITO_USER_POOL_ID = "eu-central-1_BYmQ2VBlo"
_COGNITO_AUDIENCE = "7sc1qltkqobo3ddqsk4542dg2h"
_COGNITO_JWKS_URL = f"https://cognito-idp.{_COGNITO_REGION}.amazonaws.com/{_COGNITO_USER_POOL_ID}/.well-known/jwks.json"

async def _ensure_valid_token_async():
    """Vérifie la validité du token JWT et le renouvelle si expiré."""
    logging.debug("Checking JWT validity")
    try:
        async with aiohttp.ClientSession() as session:
            async with session.get(_COGNITO_JWKS_URL) as resp:
                jwks = await resp.json()

        kid = jwt.get_unverified_header(_token).get("kid")
        key = next((k for k in jwks["keys"] if k["kid"] == kid), None)
        if key is None:
            raise Exception(f"JWT key id '{kid}' not found in JWKS")

        public_key = jwt.algorithms.RSAAlgorithm.from_jwk(key)
        jwt.decode(_token, public_key, audience=_COGNITO_AUDIENCE, algorithms=["RS256"])
        logging.info("Token valid")
    except jwt.ExpiredSignatureError:
        logging.info("Token expired, renewing...")
        await _login_async(_email, _password)
    except Exception as e:
        logging.error("Token validation error: %s", e)

async def _login_async(username, password):
    """Login and return token."""
    global _token
    try:
        # sign_in est synchrone dans la lib edilkamin
        _token = edilkamin.sign_in(username, password)
        logging.info("Logged in to Edilkamin API")
    except Exception as e:
        logging.error("Login failed: %s", e)

async def _device_info_async(macaddress):
    try:
        await _ensure_valid_token_async()
        result = edilkamin.device_info(_token, macaddress)
        # device_info peut être sync ou async selon la version de la lib
        if inspect.isawaitable(result):
            result = await result
        return json.dumps(result).replace('\\', '')
    except Exception as e:
        logging.error("[device_info] error: %s", e)
    return None

def validJWT():
    asyncio.run(_ensure_valid_token_async())

def login(username, password):
    asyncio.run(_login_async(username, password))

def device_info(macaddress):
    return asyncio.run(_device_info_async(macaddress))

_ALARM_TYPE_MAP = {
    0:  'Aucune alarme',
    1:  'Entrée d\'air insuffisante',
    2:  'RPM ventilateur fumées incorrect',
    3:  'Pas de flamme',
    4:  'Échec allumage',
    5:  'Capteur débit d\'air défaillant',
    6:  'Thermocouple défaillant',
    7:  'Température fumées trop élevée',
    8:  'Température poêle trop élevée',
    9:  'Moto-réducteur défaillant',
    10: 'Carte électronique trop chaude',
    11: 'Pression cheminée',
    12: 'Sonde température ambiante défaillante (1)',
    13: 'Sonde température ambiante défaillante (2)',
    14: 'Sonde température ambiante défaillante (3)',
    20: 'Triac moto-réducteur défaillant',
    21: 'Coupure de courant',
}
_MAX_ALARMS_DISPLAY = 5

def _format_last_alarms(alarms_log: dict) -> str:
    """Retourne les N dernières alarmes non nulles sous forme lisible."""
    alarms = [a for a in alarms_log.get('alarms', []) if a['timestamp'] > 0]
    alarms_sorted = sorted(alarms, key=lambda a: a['timestamp'], reverse=True)
    lines = []
    for alarm in alarms_sorted[:_MAX_ALARMS_DISPLAY]:
        dt = datetime.fromtimestamp(alarm['timestamp']).strftime('%d/%m/%Y %H:%M')
        label = _ALARM_TYPE_MAP.get(alarm['type'], f"Type {alarm['type']}")
        lines.append(f"{dt} - {label}")
    return '\n'.join(lines) if lines else 'Aucune alarme'

# Intervalles de rafraîchissement adaptatifs (secondes)
_REFRESH_INTERVAL_OFF        = 300   # Éteint : 5 min
_REFRESH_INTERVAL_ON         = 120   # Allumé stable : 2 min
_REFRESH_INTERVAL_TRANSITION = 30    # Phases transitoires / alarme : 30s

def _get_refresh_interval(json_info: dict) -> int:
    """Retourne l'intervalle de rafraîchissement adapté à l'état courant du poêle."""
    try:
        stove_state = json_info['status']['state']['stove_state']
        operational_phase = json_info['status']['state']['operational_phase']
        if stove_state == 1 and operational_phase == 0:
            # Éteint au repos
            return _REFRESH_INTERVAL_OFF
        elif stove_state == 6 and operational_phase == 2:
            # Allumé en fonctionnement stable
            return _REFRESH_INTERVAL_ON
        else:
            # Allumage, extinction, refroidissement, alarme → phases transitoires
            return _REFRESH_INTERVAL_TRANSITION
    except Exception:
        return _REFRESH_INTERVAL_ON

_PHASE_MAP = {
    # (stove_state, operational_phase, sub_operational_phase): label
    # stove_state 1 = Eteint / Refroidissement
    (1, 0, 0): 'Eteint',
    (1, 3, 0): 'Refroidissement',
    # stove_state 2 = Extinction
    (2, 0, 0): 'Extinction',
    # stove_state 3 = Arrêt
    (3, 0, 0): 'Arrêt',
    # stove_state 4 = Refroidissement final
    (4, 0, 0): 'Refroidissement final',
    # stove_state 5 = Alarme
    (5, 0, 0): 'Alarme',
    # stove_state 6 = En fonctionnement
    (6, 1, 1): 'Allumage : Nettoyage',
    (6, 1, 2): 'Allumage : Préchauffage',
    (6, 1, 3): 'Allumage : Chargement pellets',
    (6, 1, 4): 'Allumage : Attente flamme',
    (6, 2, 1): 'Allumé : Montée en puissance',
    (6, 2, 2): 'Allumé',
    (6, 2, 3): 'Allumé : Modulation',
    (6, 3, 0): 'Nettoyage en cours',
    # stove_state 7 = Nettoyage final
    (7, 0, 0): 'Nettoyage final',
}

def refresh(info: dict):
    try:
        state = info['status']['state']
        nb_fans = info['nvm']['installer_parameters']['fans_number']
        phase_key = (state['stove_state'], state['operational_phase'], state['sub_operational_phase'])
        logging.debug('Phase key: %s', phase_key)

        return {
            'state':                edilkamin.device_info_get_power(info).value,
            'temperature':          edilkamin.device_info_get_environment_temperature(info),
            'alarm_type':           edilkamin.device_info_get_alarm_reset(info),
            'manual_power_level':   edilkamin.device_info_get_manual_power_level(info),
            'pellet_autonomy_time': edilkamin.device_info_get_autonomy_time(info),
            'actual_power':         state['actual_power'],
            'is_auto':              info['nvm']['user_parameters']['is_auto'],
            'is_relax':             edilkamin.device_info_get_relax_mode(info),
            'target_temperature':   edilkamin.device_info_get_target_temperature(info),
            'phase':                _PHASE_MAP.get(phase_key, 'Inconnu'),
            # Compteurs
            'power_ons':            info['nvm']['total_counters']['power_ons'],
            # Maintenance
            'last_refresh':         datetime.now().strftime('%d/%m/%Y %H:%M:%S'),
            # Flags
            'is_pellet_in_reserve': info['status']['flags']['is_pellet_in_reserve'],
            'is_crono_active':      info['status']['flags']['is_crono_active'],
            'is_standby_active':    info['nvm']['user_parameters']['is_standby_active'],
            'is_airkare_active':    info['status']['flags']['is_airkare_active'],
            **{f'fan{i+1}': edilkamin.device_info_get_fan_speed(info, i+1) for i in range(nb_fans)},
        }
    except Exception as e:
        logging.error('[Refresh] %s', e)

def _run(result):
    """Exécute une coroutine edilkamin si async, sinon retourne le résultat directement."""
    if inspect.isawaitable(result):
        return asyncio.run(result)
    return result

def device_info_json(macaddress):
    """Retourne les infos du device sous forme de dict."""
    return json.loads(device_info(macaddress))

def _wait_for_state(macaddress, forJeedom, target: dict):
    """Boucle jusqu'à ce que le poêle atteigne l'état cible, en remontant les infos à Jeedom."""
    while True:
        info = device_info(macaddress)
        json_info = json.loads(info)
        forJeedom['infos'] = info
        forJeedom['refresh_infos'] = refresh(json_info)
        my_jeedom_com.send_change_immediate(forJeedom)
        state = json_info['status']['state']
        if all(state[k] == v for k, v in target.items()):
            break
        time.sleep(30.0)

def _handle_post_save(message, forJeedom):
    forJeedom['eqlogicid'] = message.get('eqlogicid')
    # Mémoriser la MAC pour le rafraîchissement autonome
    _known_devices[message['macaddress']] = message.get('eqlogicid')
    # device_info est récupéré ici pour permettre la création automatique des commandes
    # Le JSON complet est transmis au callback PHP qui se charge du mapping
    info = device_info(message['macaddress'])
    forJeedom['infos'] = info
    forJeedom['refresh_infos'] = refresh(json.loads(info))

def _handle_power_on(message, forJeedom):
    _run(edilkamin.set_power_on(_token, message['macaddress']))
    _wait_for_state(message['macaddress'], forJeedom,
                    {'stove_state': 6, 'operational_phase': 2, 'sub_operational_phase': 2})

def _handle_power_off(message, forJeedom):
    _run(edilkamin.set_power_off(_token, message['macaddress']))
    _wait_for_state(message['macaddress'], forJeedom,
                    {'stove_state': 1, 'operational_phase': 0, 'sub_operational_phase': 0})

def _handle_fan_speed(message, forJeedom):
    info = device_info_json(message['macaddress'])
    fan_id = int(message['action'][-1])
    current = info['status']['fans'][f'fan_{fan_id}_speed']
    target = int(message['speed'])
    if current != target:
        _run(edilkamin.set_fan_speed(_token, message['macaddress'], fan_id, target))
    else:
        logging.debug('Fan%i already set to %i', fan_id, target)

def _handle_manual_power(message, forJeedom):
    info = device_info_json(message['macaddress'])
    current = info['status']['state']['actual_power']
    target = int(message['manual_power'])
    if current != target:
        _run(edilkamin.set_manual_power_level(_token, message['macaddress'], target))
    else:
        logging.debug('Power already set to %i', target)

def _handle_auto_mode(message, forJeedom):
    value = message['action'] == 'set_auto_on'
    _run(edilkamin.mqtt_command(_token, message['macaddress'], {"name": "auto_mode", "value": value}))

def _handle_relax_mode(message, forJeedom):
    value = message['action'] == 'set_relax_on'
    _run(edilkamin.set_relax_mode(_token, message['macaddress'], value))

def _handle_target_temperature(message, forJeedom):
    _run(edilkamin.set_target_temperature(_token, message['macaddress'], int(message['target_temperature'])))

_ACTION_HANDLERS = {
    'postSave':             _handle_post_save,
    'set_power_on':         _handle_power_on,
    'set_power_off':        _handle_power_off,
    'set_auto_on':          _handle_auto_mode,
    'set_auto_off':         _handle_auto_mode,
    'set_relax_on':         _handle_relax_mode,
    'set_relax_off':        _handle_relax_mode,
    'set_target_temperature': _handle_target_temperature,
}

def _get_handler(action):
    if action in _ACTION_HANDLERS:
        return _ACTION_HANDLERS[action]
    if action.startswith('fan_speed'):
        return _handle_fan_speed
    if action.startswith('manual_power'):
        return _handle_manual_power
    return None

def read_socket():
    if JEEDOM_SOCKET_MESSAGE.empty():
        return
    logging.debug("Message received in socket JEEDOM_SOCKET_MESSAGE")
    message = json.loads(JEEDOM_SOCKET_MESSAGE.get())
    if message['apikey'] != _apikey:
        logging.error("Invalid apikey from socket: %s", message)
        return
    try:
        if not message.get('macaddress'):
            raise Exception("Mac Address is empty!")

        forJeedom = {}
        action = message['action']
        handler = _get_handler(action)
        if handler:
            handler(message, forJeedom)
        else:
            logging.warning("Unknown action: %s", action)

        # mac_address toujours transmis pour que le PHP identifie l'eqLogic
        forJeedom['mac_address'] = message['macaddress']

        # Pour postSave, infos déjà remplies par le handler
        if 'infos' not in forJeedom:
            time.sleep(1.0)
            info = device_info(message['macaddress'])
            forJeedom['infos'] = info
            forJeedom['refresh_infos'] = refresh(json.loads(info))
        my_jeedom_com.send_change_immediate(forJeedom)
    except Exception as e:
        logging.error('Send command to demon error: %s', e)


def listen():
    my_jeedom_socket.open()
    last_refresh = 0
    current_interval = _REFRESH_INTERVAL_ON
    try:
        while 1:
            time.sleep(0.5)
            read_socket()
            now = time.time()
            if now - last_refresh >= current_interval and _known_devices:
                logging.debug('Auto-refresh (interval=%ds)', current_interval)
                for macaddress in list(_known_devices.keys()):
                    try:
                        forJeedom = {'mac_address': macaddress}
                        info = device_info(macaddress)
                        json_info = json.loads(info)
                        forJeedom['infos'] = info
                        forJeedom['refresh_infos'] = refresh(json_info)
                        my_jeedom_com.send_change_immediate(forJeedom)
                        current_interval = _get_refresh_interval(json_info)
                        logging.debug('Prochain refresh dans %ds', current_interval)
                    except Exception as e:
                        logging.error('Auto-refresh error for %s: %s', macaddress, e)
                last_refresh = now
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
_refresh_interval = 300  # secondes, modifiable via --refreshinterval
_known_devices = {}  # {macaddress: eqlogicid}

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
parser.add_argument("--refreshinterval", help="Auto-refresh interval in seconds", type=int)
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
if args.refreshinterval:
    _refresh_interval = args.refreshinterval

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
    login(_email, _password)
    listen()
except Exception as e:
    logging.error('Fatal error: %s', e)
    logging.info(traceback.format_exc())
    shutdown()
