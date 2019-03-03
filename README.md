```
wget http://downloads.mysql.com/docs/sakila-db.zip
unzip sakila-db.zip 
cat sakila-db/sakila-schema.sql sakila-db/sakila-data.sql > both.sql
composer install
docker-compose up
composer test
```