Запуск проекта:
Установить postgresql, создать БД "create database db_name owner username"
Указать в файле .env данные для подключения к БД.
пример
DATABASE_URL="postgresql://пользователь:пароль@серверБД/ИмяБД?serverVersion=15&charset=utf8"

в папке проекта через терминал
выполнить миграции
php bin/console doctrine:migrations:migrate

Запуск на windows - из папки проекта 
php -S localhost:8000 -t public

