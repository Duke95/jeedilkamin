import argparse
import edilkamin

print("start")
parser = argparse.ArgumentParser()
parser.add_argument("--email")
parser.add_argument("--password")
#parser.add_argument("--action")
#parser.add_argument("--value", default=None)
args = parser.parse_args()

print("email: ", args.email)

token = edilkamin.sign_in(args.email, args.password)
print(token)

#if args.action == "power_on":
#    stove.power_on()
#elif args.action == "power_off":
#    stove.power_off()
#elif args.action == "set_power_level":
#    stove.set_power_level(int(args.value))
#elif args.action == "set_target_temperature":
#    stove.set_target_temperature(float(args.value))
#elif args.action == "set_fan_speed":
#    stove.set_fan_speed(int(args.value))
#elif args.action == "get_status":
#    print(stove.get_status())
