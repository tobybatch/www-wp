# www-wp


On the servers:

```shell
source .env && docker compose exec nrfc-wp-db \
    mariadb-dump \
        -u${WORDPRESS_DB_USER} \
        -p${WORDPRESS_DB_PASSWORD} \
        ${WORDPRESS_DB_NAME} \
    > backups/dump-$(date +"%y-%m-%d").sql
```

On local

```shell
scp www.norwichrugby.com/opt/docker/www-wp/backups/dump-$(date +"%y-%m-%d").sql ./initdb.d/dump.sql
```

```shell
docker compose -f ./dev.compose.yaml up -d
docker compose -f dev.compose.yaml exec nrfc-wordpress wp user update superadmin --user_pass="MyNewPass123"
docker compose -f dev.compose.yaml exec nrfc-wordpress wp search-replace 'https://dev.norwichrugby.com' 'http://localhost:8088'
source .env && docker compose exec nrfc-wp-db mariadb -u${WORDPRESS_DB_USER} -p${WORDPRESS_DB_PASSWORD} ${WORDPRESS_DB_NAME} -e "SET option_value = 'http://localhost:8088' WHERE option_name IN ('siteurl', 'home');"
docker compose -f dev.compose.yaml exec nrfc-wordpress wp cache flush
```

http://localhost:8088/wp-admin - superadmin / MyNewPass123
