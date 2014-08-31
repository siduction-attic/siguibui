#!/bin/sh
# Switches automounting on or off (for gnome, cinnamon and mate)
#set -x
date "+%Y.%m.%d %H:%M:%S: $1" >>/tmp/automount.log

if [ -f /etc/default/distro ]; then
    . /etc/default/distro
fi

get_dbus_session()
{
    session=$(pgrep -u "${FLL_LIVE_USER}" session)
    export $(grep -z DBUS_SESSION_BUS_ADDRESS /proc/$session/environ)
}

case "$1" in
    enabled)
        SET_AUTOMOUNT=true
        ;;
    disabled)
        SET_AUTOMOUNT=false
        ;;
    *)
        echo "automount-control.sh called with unknown argument \`$1'" >&2
        exit 1
        ;;
esac

case "$FLL_FLAVOUR" in
    gnome)
        get_dbus_session
        su ${FLL_LIVE_USER} -c "gsettings set org.gnome.desktop.media-handling automount $SET_AUTOMOUNT"
        su ${FLL_LIVE_USER} -c "gsettings set org.gnome.desktop.media-handling automount-open $SET_AUTOMOUNT"
        ;;
    cinnamon)
        get_dbus_session
        su ${FLL_LIVE_USER} -c "gsettings set org.cinnamon.desktop automount $SET_AUTOMOUNT"
        su ${FLL_LIVE_USER} -c "gsettings set org.cinnamon.desktop automount-open $SET_AUTOMOUNT"
        ;;
    mate)
        get_dbus_session
        su ${FLL_LIVE_USER} -c "gsettings set org.mate.media-handling automount $SET_AUTOMOUNT"
        su ${FLL_LIVE_USER} -c "gsettings set org.mate.media-handling automount-open $SET_AUTOMOUNT"
        ;;
esac
#set +x
