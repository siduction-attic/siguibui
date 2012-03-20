#! /bin/bash
# build.sh sidu-usb-installer /home/wsl6/php hm /home/wsl6/php git

PROJ=$1
BASEDIR=$2
USER=$3
BASEBUI=$4
GIT=$5

if [ -z "$BASEBUI" ] ; then
  echo "Usage: build.sh PROJ BASEDIR USER BASEBUI [GIT]"
  echo "Example: build.sh sidu-disk-center ~/src jonny ~/src git"
  exit 1
fi
if [ -z "$(which dh_make)" ] ; then
	echo "not installed: dh-make"
	exit 1
fi
test -z "$BASEBUI" && BASEBUI=/usr/share
DIRBUI=$BASEBUI/siguibui
TODO=/tmp/siguibui.todo.txt
test -f $TODO && rm $TODO

if [ ! -d "$BASEDIR" ] ; then
  echo "BASEDIR: Not a directory: $BASEDIR"
  exit 1
fi
function Copy(){
  SRC=$1
  TRG=$2
  if [ ! -f $TRG ] ; then
    cp $SRC $TRG
    echo "$SRC -> $TRG"
  fi
}
function GenFile(){
  SRC=$1
  TRG=$2
  if [ ! -f $TRG ] ; then
    echo "$TEXT" >$TRG
    echo "New: $TRG"
  fi
}
function replaceProj(){
  fn=$1
  sed -i -e "s/#!PROJ!#/$PROJ/g" $fn
}
DIR=$BASEDIR/$PROJ
test -d $DIR || mkdir $DIR

pushd $DIR >/dev/null
if [ ! -d $DIRBUI ] ; then
  echo "BASEBUI: no siguibui subdirectory found: $BASEBUI"
  popd >/dev/null
  exit 1
fi

echo Building directories...
mkdir -p backend config images plugins etc/pywwetha/virtualhosts.d etc/$PROJ usr/bin
echo Linking from siguibui...
test -e index.php || ln -s $DIRBUI/index.php .
test -e base || ln -s $DIRBUI/base .
Copy  $DIRBUI/build/backend/example.sh backend/example.sh
for tail in .conf _de.conf .css .html ; do
  trg=config/$PROJ$tail
  Copy $DIRBUI/build/config/siguibui$tail $trg
  grep '#!PROJ!#' $trg && replaceProj $trg
done
GenFile "# Configuration of $PROJ" etc/$PROJ/$PROJ.conf
FN=etc/pywwetha/$PROJ.conf
if [ ! -f $FN ] ; then
  echo "New: $FN"
  cat <<EOS >$FN
# The path where the documents are lying
$PROJ:documentRoot=/usr/share/$PROJ

# The default resource if no file is given:
$PROJ:index=index.php

# The program to build dynamic content
$PROJ:cgiProgram=/usr/bin/php-cgi

# Arguments for the cgi program: separated by |
# Possible placeholder: \${file} (the input for the cgi program)
$PROJ:cgiArgs=\${file}|-C|-c|/etc/wywetha/php.ini

# File extensions of the CGI scripts, separated by |
$PROJ:cgiExt=php|php5hm
EOS
fi
GenFile "" etc/pywwetha/virtualhosts.d/$PROJ
for file in favicon.ico logo.png ; do
  Copy $DIRBUI/build/images/$file images/$file
done

for file in home.content.txt homepage.php ; do
  Copy $DIRBUI/build/plugins/$file plugins/$file
done
FN=/tmp/build.tmp.$$
cat >$FN <<EOS
#! /bin/sh

# Restart forces the check of virtual hosts:
sudo /etc/init.d/pywwetha stop
sudo /etc/init.d/pywwetha start
sudo /etc/init.d/$PROJ start
x-www-browser http://$PROJ:8086
EOS
Copy $FN usr/bin/$PROJ.sh

test -z $USER && chown -R $USER ../$PROJ

if [ ! -d debian ] ; then
  if [ -z "$DEBEMAIL" ] ; then
    echo >>$TODO "set DBEMAIL and DEBFULLNAME and run dh_make -s -p $PROJ_0.1 -c gpl --createorig"
  else
    dh_make -s -p ${PROJ}_0.1 -c gpl --createorig
    rm debian/*.ex debian/*.EX debian/README.Debian debian/README.source
  fi
  echo <<EOS >debian/install
index.php	usr/share/$PROJ
base		usr/share/$PROJ
backend		usr/share/$PROJ
etc		etc
usr		usr
EOS
fi
if [ "$GIT" = "git" -a ! -d .git ] ; then
  git init
  cat <<EOS >.gitignore
index.php
base
base/*
EOS
  git add backend/* config/* debian/* 
  git add etc/$PROJ/* etc/pywwetha/* etc/pywwetha/virtualhosts.d/*
  git add images/* plugins/* usr/bin/* .gitignore
fi
popd >/dev/null
test -f $TODO && cat $TODO