import time
import requests
from edilkamin import Edilkamin

JEEDOM_URL = "http://127.0.0.1/plugins/edilkamin/core/php/edilkamin_event.php"
EQLOGIC_ID = 12

stove = Edilkamin(host="192.168.1.50", token="XXXX")

def send_status():
    status = stove.get_status()

    payload = {
        "eqLogic_id": EQLOGIC_ID,
        "datas": {
            "state": 1 if status.is_on else 0,
            "power_level": status.power,
            "temperature_room": status.temperature,
            "temperature_target": status.target_temperature,
            "fan_speed": status.fan_speed,
            "status": status.status,
            "pellet_level": status.pellet_level,
            "error": status.error
        }
    }

    requests.post(JEEDOM_URL, json=payload, timeout=3)

while True:
    try:
        send_status()
    except Exception as e:
        print("Erreur:", e)

    time.sleep(10)
