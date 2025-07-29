# www-wp

```shell
docker \
    compose exec db \
    mariadb-dump \
        --user=root \
        --password=password \
        --lock-tables \
        wordpress \
    > initdb.d/00_init.sql
```