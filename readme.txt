Запуск проекта:
Установить postgresql, создать БД "create database db_name owner username"
Указать в файле .env данные для подключения к БД.


в папке проекта через терминал
выполнить миграции
php bin/console make:migration
php bin/console doctrine:migrations:migrate