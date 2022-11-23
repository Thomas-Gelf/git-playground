nodes:
listrequiredservices
providedservices


Installation
============

```shell
SOCKET_PATH=/run/imedge-node
TMPFILES_CONFIG=/etc/tmpfiles.d/imedge-node.conf
DAEMON_NAME="imedge-node"
DAEMON_USER="imedge"
DAEMON_GROUP="imedge"
WEB_USER="icingaweb2"

getent group "${DAEMON_GROUP}" > /dev/null || groupadd "${DAEMON_GROUP}"
getent passwd "${DAEMON_USER}" > /dev/null || useradd -r -g "${DAEMON_GROUP}" \
-d /var/lib/${DAEMON_USER} -s /sbin/nologin ${DAEMON_USER}
getent passwd "${WEB_USER}" > /dev/null && usermod -a -G "${DAEMON_GROUP}" "${WEB_USER}"
install -d -o "${DAEMON_USER}" -g "${DAEMON_GROUP}" -m 0750 /var/lib/${DAEMON_USER}

echo "d /run/${DAEMON_NAME} 0755 ${DAEMON_USER} ${DAEMON_GROUP} -" > "/etc/tmpfiles.d/${DAEMON_NAME}.conf"

echo "d ${SOCKET_PATH} 0755 ${DAEMON_USER} ${DAEMON_GROUP} -" > "${TMPFILES_CONFIG}"
systemd-tmpfiles --create "${TMPFILES_CONFIG}"

# Customer: done bis hier, unit-file fehlt

# cp -f "${TARGET_DIR}/contrib/systemd/icinga-datanode.service" /etc/systemd/system/
# systemctl daemon-reload
# systemctl enable icinga-datanode.service
# systemctl restart icinga-datanode.service
```


On RHEL, base
-------------

dnf install rrdtool redis
dnf --enablerepo=epel install https://rpms.remirepo.net/enterprise/remi-release-8.rpm
dnf config-manager --set-disabled remi-modular --set-disabled remi-safe
dnf install php81 php81-php-pecl-event php81-php-pecl-ev php81-php-gmp php81-php-intl php81-php-ldap php81-php-mysqlnd php81-php-mbstring php81-php-pdo php81-php-sodium php81-php-xml php81-php-soap php81-php-phpiredis php81-php-process --enablerepo=remi-safe,remi-modular,epel
# Problem with wrong config ini files on Neteye:
export PHP_INI_SCAN_DIR=
