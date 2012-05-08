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
sudo /etc/init.d/pywwetha restart
sidu-shellserver-control start
x-www-browser http://$PROJ:8086
EOS
Copy $FN usr/bin/$PROJ-control

test -z $USER && chown -R $USER ../$PROJ

if [ ! -d debian ] ; then
  if [ -z "$DEBEMAIL" ] ; then
    echo >>$TODO "set DBEMAIL and DEBFULLNAME and run dh_make -s -p $PROJ_0.1 -c gpl --createorig"
  else
    dh_make -s -p ${PROJ}_0.1 -c gpl --createorig
    rm debian/*.ex debian/*.EX debian/README.Debian debian/README.source
    # Replace the "quilt" entry: too complicated
    echo "3.0 (native)" >debian/source/format
    sed -i -e 's/Architecture: any/Architecture: all/;s/Depends: ${shlibs:Depends}, ${misc:Depends}/Depends: ${misc:Depends},\n   siguibui (>= 1.0.0)/;' debian/control
    echo >>$TODO "* Edit debian/control"
    echo >>$TODO "* Edit debian/copyright"
    #mkdir tarball
    #cp ../$PROJ*.gz tarball
  fi
  cat <<EOS >$FN
backend		usr/share/$PROJ
config		usr/share/$PROJ
plugins		usr/share/$PROJ
images		usr/share/$PROJ
etc/$PROJ	etc
etc/pywwetha	etc
usr/bin		usr
EOS
	Copy $FN debian/install
	cat <<EOS >$FN
#! /bin/bash
set -e
PROJECT=$PROJ
if ! grep -q "\$PROJECT" /etc/hosts ; then
	# if the last newline is missing:
	echo "" >>/etc/hosts
	ADDR=86
	while grep -q "127.0.0.\$ADDR" /etc/hosts ; do
		ADDR=\$(expr \$ADDR + 1)
	done
	echo "127.0.0.\$ADDR \$PROJECT" >>/etc/hosts
	echo "virtual host installed: \$PROJECT (\$ADDR)"
fi

# Create the links from the toolkit:
pushd /usr/share/\$PROJECT >/dev/null
SRC=../siguibui
function MkLink(){
	if [ ! -L \$1 ] ; then
		ln -s \$2/\$1 \$1
	fi
}
function MkLink2(){
	if [ ! -L \$2 ] ; then
		ln -s \$1 \$2
	fi
}
MkLink index.php \$SRC
MkLink base \$SRC

# Create the links to the shellserver:
cd /usr/share/siguibui/backend
SRC=../../\$PROJECT/backend
MkLink example.sh \$SRC
MkLink2 /var/cache/siguibui/public /usr/share/\$PROJECT
popd >/dev/null
#DEBHELPER#
exit 0
EOS
	Copy $FN debian/postinst
	cat <<EOS >$FN
#! /bin/bash
set -e
PROJECT=$PROJ
if grep -q \$PROJECT /etc/hosts ; then
	sed -i -e "/127\.0\.0\.[0-9]*[ \t]*\$PROJECT/d" /etc/hosts
	echo "virtual host removed: \$PROJECT"
fi

# delete the links from the toolkit:
function RmLink(){
	if [ -L \$1 ] ; then
		rm -f \$1
	fi
}
pushd /usr/share/\$PROJECT >/dev/null
RmLink index.php
RmLink base
RmLink public

# Remove the links to the shellserver:
cd /usr/share/siguibui/backend
RmLink example.sh
popd >/dev/null
#DEBHELPER#
exit 0
EOS
	Copy $FN debian/prerm	
fi
if [ "$GIT" = "git" -a ! -d .git ] ; then
  git init
  cat <<EOS >.gitignore
index.php
base
base/*
.settings/*
.classpath
.project
*~
EOS
  git add backend/* config/* debian/* 
  git add etc/$PROJ/* etc/pywwetha/* etc/pywwetha/virtualhosts.d/*
  git add images/* plugins/* usr/bin/* .gitignore
  #git add tarball/*
  git commit -m "Freshly built"
fi
popd >/dev/null
test -f $TODO && cat $TODO
