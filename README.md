# How to reproduce the problem with phpamqplib <-> dead proxy (haproxy|vpn) <-> rabbitmq

#### Reproduce steps:

* Clone and checkout this repo to `master` branch.
* `$ composer install --dev`
* `$ ./play.sh`
    * this script runs the dying proxy
    * runs consumer script
    * after 10 seconds running proxy will send "space" character for emulation a broken connection between proxy and rabbitmq "proxy <-> rabbit", and will die
* timeout for consumer is configured to 60 seconds, so that consumer must be stopped after 60 seconds proxy dying (but this will not happen)
* After that please wait 30 seconds for detecting the problem.
* consumer is stopped?

##### Results:
* If you see repeatable message "DETECT PROBLEM!", then problem with dead proxy is reproduced.
    * this message repeated during 80 seconds, after that proxy is shutdown's, and consumer script will falling  
* If you see repeatable message "GOOD WORK!", then problem is not detected

#### Testing the fix for this problem:

* Checkout this repo to `fixed` branch.
* `$ composer install --dev`
* `$ ./play.sh`
    * this script runs the dying proxy
    * runs consumer script
    * after 10 seconds running proxy will send "space" character for emulation a broken connection between proxy and rabbitmq "proxy <-> rabbit", and will die
* timeout for consumer is configured to 60 seconds, timeout for failing repeatable reads is 30 seconds (hardcode in constant), so that consumer must be fall through 30 seconds after proxy dying
* After that please wait 30 seconds for detecting the problem.
* consumer is fell?

##### Results:
* If you see repeatable message "DETECT PROBLEM!", then problem with dead proxy is reproduced, fix is not worked.
    * this message repeated during 60 seconds, after that proxy will be shutdowned, and consumer script will fall
* If you see repeatable message "GOOD WORK!", then problem is not detected, and problem is fixed by @parpalak fix.
