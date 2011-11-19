#! /bin/bash
ANSWER=$1
APPL=$2
ARGS=$3
USER=$4
OPTS=$5
ARGS=$(echo $ARGS | sed -e "s/''//g")
CONFIG=/etc/sidu-installer/shellserver.conf

test -f $CONFIG && source $CONFIG
test -n "$START_GUI_HOME" && export HOME=$START_GUI_HOME
test -d $HOME || export HOME=$START_GUI_HOME2

if [ -z "$CONSOLE" ] ; then
	CONSOLE=konsole
	CONSOLE_ARGS=-e
fi
test -n "$VERBOSE" && echo "startgui: user=$USER appl=$APPL opts=$OPTS console=$CONSOLE home=$HOME"

if [ -z "$DISPLAY" ] ; then
	export DISPLAY=:0
fi
FOUND=$(echo $OPTS | grep -i console)
if [ -n "$FOUND" ] ; then
	sux $USER $CONSOLE $CONSOLE_ARGS $APPL $ARGS
	AGAIN=1
	while [ -n "$AGAIN" ] ; do
		set -x
		AGAIN=$(ps aux | grep $APPL | grep -v grep | grep -v $0)
		set +x
		sleep 1
	done
else
	sux $USER $APPL $ARGS
fi
echo $? >$ANSWER
chmod uog+w $ANSWER
echo $ANSWER ready!
