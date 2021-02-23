Тестовое задание для PHP-программиста (MIDDLE-уровень)
======================================================

Описание задания и список задач смотрите [README.ORIG](README.ORIG.md)

Установка и настройка
---------------------

Клонируйте репозиторий
```console
git clone https://github.com/i-tools/crtweb.trailers.git
```

Перейдите в папку
```console
cd crtweb.trailers
```

В редакторе поменяйте настройки доступа к БД в файле `.env`
```
DATABASE=mysql://user:password@localhost:3306/database
```

Установите зависимости
```console
composer install
```
И создайте базу данных если она не создана
```console
bin/console orm:database:create
```

Импорт исходных данных
----------------------

Для импорта данных используйте команду
```console
bin/console fetch:trailers
```
Для команды доступну следующие параметры
```
Arguments:
  source                URL исходных данных (по умолчанию https://trailers.apple.com/trailers/home/rss/newtrailers.rss

Options:
  -c, --count[=COUNT]   Кол-во импортируемых записей (по умолчанию 10)
  -l, --import-last     Опция указывает что необходимо импортировать последние записи
```


Запуск
------

Для запуска консольного сервера выполните команду
```console
composer run server.start
```
после выполнения Вы увидите примерно следующее
```console
> php -S localhost:8080 -t public public/index.php
[Tue Feb 23 09:56:52 2021] PHP 8.0.2 Development Server (http://localhost:8080) started
```
далее в браузере можно открыть страницу по адресу http://localhost:8080