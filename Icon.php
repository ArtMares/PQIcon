<?php

/**
 * @author  ArtMares (Dmitriy Dergachev)
 * @date    07.04.2016
 */
class Icon {
    /** Хранилище */
    private $storage;
    /** Классы Qt, расширения PQEngineFS */
    private $file, $dir;
    /** Корневая директория приложения */
    private $documentRoot;
    /** Директория в которой будут хранится подготовленные файлы шрифтов */
    private $iconPath;
    /** Массив иконок в виде UTF-8 кодов */
    static $icons = array();

    /**
     * Icon constructor.
     * @param string $path - Дочерний каталог в котормо хранится файл конфигурации и подготовленые шрифты
     */
    public function __construct($path = '') {
        /** Получаем корневую директорию приложения */
        $this->documentRoot = qApp::applicationDirPath().(!empty($path) ? "/$path" : '');
        /** Делаем проверку на наличии всех необходимыз расширений и аддонов */
        $this->checkDepend();
        /** Загружаем шрифты в память */
        $this->init();
    }

    /**
     * Метод checkDepend() - Производит проверку на зависисмости
     */
    private function checkDepend() {
        /** Проверяем наличии подключенного аддона Хранилище */
        if(class_exists('Storage')) {
            /** Загружаем хранилище если аддон подключен */
            $this->storage = loadStorage();
            /** Инициализируем хранилище шрифтов Qt */
            $this->storage->FontDatabase = new QFontDatabase;
        } else {
            /** Выводим сообщение о ошибке и завершаем работу приложения */
            die("Error!\r\nNeed to connect Add-on \"Storage\"!");
        }
        /** Проверяем наличие подключено расширения PQEngineFS */
        if(class_exists('QDir') && class_exists('QFile')) {
            /** Если расширение доступно, то инциализируем классы расширения */
            $this->dir = new QDir();
            $this->file = new QFile();
        } else {
            /** Выводим сообщение о ошибке и завершаем работу приложения */
            die("Error!\r\nNeed to build the project with extension \"PQEngineFS\"!");
        }
    }

    /**
     * Метод init() - Запускает инициализацию для получения настроек и подключает шрифты
     */
    private function init() {
        /** Задаем путь к конфигурационному файлу */
        $this->file->setFileName($this->documentRoot.'/iconFonts.json');
        /** Проверяем наличие файла конфигурации */
        if($this->file->exists()) {
            /** Открываем файл конфигурации */
            if($this->file->open(QFile::ReadOnly)) {
                /** Получаем данные из конфигурационного файла */
                $data = json_decode($this->file->readAll());
                /** Закрываем файл конфигурации так как он больше не нужен */
                $this->file->close();
                /** Проверяем на наличии данных из файла */
                if(!empty($data) && is_object($data)) {
                    /**
                     * Создаем директории для хранения уже распарсеных файлов
                     * Необходимо для того чтобы не парсиль файлы при каждом запуске приложения
                     */
                    $this->dir->mkdir($this->documentRoot.'/fonts');
                    $this->dir->mkdir($this->documentRoot.'/fonts/parsed');
                    $this->iconPath = $this->documentRoot.'/fonts/parsed';
                    /** Делаем обработку конфигурационных данных */
                    foreach($data as $name => $font) {
                        /** Проверяем что каждый ключ со значением оформлен правильно и существует */
                        if(is_object($font) && isset($font->path) && isset($font->resource)) {
                            /** Запускаем метод предназначеный для получения шрифта */
                            $this->getFont($name, $font);
                        } else {
                            /** Если хотя бы одна запись оформлена не правильно, то выводим сообщение о ощибке и завершаем работу приложения */
                            die("Error!\r\nData is file \"iconFonts.json\" are incorrect!");
                        }
                    }
                } else {
                    /** Если данных нет или они формлены не правильно, то выводим сообщение о ошибке и завершаем работу приложения */
                    die("Error!\r\nFile \"iconFonts.json\" is empty!");
                }
            } else {
                /** Если файл не возможно открыть, выводим сообщение о ошибке и завершаем работу приложения */
                die("Error!\r\nIt isn't possible to open the file \"iconFonts.json\"!");
            }
        } else {
            /** Если файла конфигруации нет, то выводим сообщение о ошибке и завершаем работу приложения */
            die("Error!\r\nNot found file \"iconFonts.json\" in path \"$this->documentRoot\"!");
        }
    }

    /**
     * Метод getFont() - Производит загрузку шрифта и подключает его в приложение
     * @param $name - Название шрифта
     * @param $data - Настройки для получения шрифта
     */
    private function getFont($name, $data) {
        /** Задаем файл с распрсенным шрифтом */
        $this->file->setFileName($this->iconPath."/$name.json");
        /** Необходимоли подключать файл шрифта из ресурсов*/
        if($data->resource == true) {
            /** Если да то подключаем файл шрифта из ресурсов */
            $this->storage->FontDatabase->addApplicationFont(R($data->path.$name.'.ttf', true));
        } else {
            /** Если нет то подключаем на прямую из директории */
            $this->storage->FontDatabase->addApplicationFont($this->documentRoot."/$data->path"."$name.ttf");
        }
        /** Проверяем на наличие распарсеного файла шрифта */
        if($this->file->exists()) {
            /** Открываем файл распарсеного шрифта только для чтения */
            $this->file->open(QFile::ReadOnly);
            /** Получаем данные из файла */
            $data = json_decode($this->file->readAll(), true);
            /** Закрываем файл */
            $this->file->close();
            /** Дополняем массив иконок */
            self::$icons = array_merge(self::$icons, $data);
        } else {
            /** Если распарсеного файла шрифта нет, то выполняем проверку на необходимость подключения файлов из ресурсов*/
            if($data->resource === true) {
                /** Получаем стили для шрифта из соответствующего файла в формате css */
                $style = file_get_contents("qrc://$data->path"."$name.css");
            } else {
                /** Задаем файл стиля для получения данных */
                $this->file->setFileName($this->documentRoot."/$data->path"."$name.css");
                /** Открываем файл */
                $this->file->open(QFile::ReadOnly);
                /** Получаем стили для шрифта */
                $style = $this->file->readAll();
                /** Закрываем файл */
                $this->file->close();
            }
            /** Удаляем из полученных стилей лишниеданные */
            $style = preg_replace(array('/\@font-face\s{0,1}\{(.*?)\}/i', '/\/\*(.*?)\*\//i'), '', str_replace(array("\r\n", "\r", "\n"), '', $style));
            /** Запускаем парсер стилей */
            $this->parseStyle($name, $style);
        }
    }

    /**
     * Метод parseStyle() - Парсит строку стилей, находит название иконки и ее UTF-8 код
     * @param $name - Имя шрифта
     * @param $style - Содержимое файла css
     */
    private function parseStyle($name, $style) {
        /** регулярное выражение которое находит имя класса и его UTF-8 код */
        $exp = '/\.(([a-z\-_]*):before\s{0,1}\{|([a-z\-_]*):after\s{0,1}\{)(\s{0,4}content:\s{0,1}\"\\\\([0-9A-Z]{2})([0-9A-Z]{2})\")/i';
        /** Выполянем поиск по регулярному выражению */
        preg_match_all($exp, $style, $match);
        /** Объявляем массив в который в дальнейшем попадут данные об иконках и их именах */
        $data = array();
        /** Проверяем были ли совпадения с регулярным выражением */
        if(isset($match[2]) && !empty($match[2]) && isset($match[5]) && !empty($match[5]) && isset($match[6]) && !empty($match[6])) {
            /** проходим по всем совпадениям */
            foreach($match[2] as $index => $key) {
                /** Удаляем лишние символы из имени иконки */
                $key = str_replace(array(':before', ':after', ' '), '', $key);
                /** Заносим исконку с ее UTF-8 кодом во временный массив */
                $data[$key] = iconv('UCS-2', 'UTF-8', chr(hexdec('\\'.$match[5][$index])).chr(hexdec('\\'.$match[6][$index])));
            }
        }
        /** Проверяем на пустоту временный массив иконок */
        if(!empty($data)) {
            /** Если массив не пуст то добавляем его в массиву иконок */
            self::$icons = array_merge(self::$icons, $data);
            /** Создаем распарсеный файл шрифта и записываем в него данные */
            $this->file->setFileName($this->iconPath . "/$name.json");
            $this->file->open(QFile::WriteOnly);
            $this->file->write(json_encode($data));
            $this->file->close();
        }
    }

    /**
     * Метод get() - Возвращает иконку или пустую строку если иконки нет в библиотеке
     * @param $name - Название иконки
     * @param null $param1 - Необязательный параметр, может быть как цветом так и размером иконки
     * @param null $param2 - Необязательный параметр, может быть как цветом так и размером иконки
     * @return string - Иконка или пустая строка
     */
    static public function get($name, $param1 = null, $param2 = null) {
        $icon = '';
        if(!is_null($param1) && !is_null($param2)) {
            if(is_string($param1) && (is_int($param2) || is_float($param2))) $icon = self::iconColorSize($name, $param1, (int)$param2);
            if((is_int($param1) || is_float($param1)) && is_string($param2)) $icon = self::iconSizeColor($name, (int)$param1, $param2);
        } else if(!is_null($param1) && is_null($param2)) {
            if(is_string($param1)) $icon = self::iconColor($name, $param1);
            if(is_int($param1) || is_float($param1)) $icon = self::iconSize($name, (int)$param1);
        } else {
            $icon = isset(self::$icons[$name]) ? self::$icons[$name] : '';
        }
        return $icon;
    }

    /**
     * Метод iconColor() - Возвращает иконку с указанным цветом
     * @param $name - Название иконки
     * @param $color - Цвет в формате воспринимаемом css
     * @return string - Иконка или пустая строка
     */
    static private function iconColor($name, $color) {
        return isset(self::$icons[$name]) ? "<span style=\"color: $color\">".self::$icons[$name]."</span>" : '';
    }

    /**
     * Метод iconSize() - Возвращает иконку с указанным размером
     * @param $name - Название иконки
     * @param $size - Размер иконки
     * @return string - Иконка или пустая строка
     */
    static private function iconSize($name, $size) {
        return isset(self::$icons[$name]) ? "<span style=\"font-size: {$size}px\">".self::$icons[$name]."</span>" : '';
    }

    /**
     * Метод iconColorSize() - Возвращает иконку с указанным цветом и размером
     * @param $name - Название иконки
     * @param $color - Цвет в формате воспринимаемом css
     * @param $size - Размер иконки
     * @return string - Иконка или пустая строка
     */
    static private function iconColorSize($name, $color, $size) {
        return isset(self::$icons[$name]) ? "<span style=\"color: {$color}; font-size: {$size}px\">".self::$icons[$name]."</span>" : '';
    }

    /**
     * Метод iconSizeColor() - Возвращает иконку с указанным цветом и размером
     * @param $name - Название иконки
     * @param $size - Размер иконки
     * @param $color - Цвет в формате воспринимаемом css
     * @return string - Иконка или пустая строка
     */
    static private function iconSizeColor($name, $size, $color) {
        return isset(self::$icons[$name]) ? "<span style=\"color: {$color}; font-size: {$size}px\">".self::$icons[$name]."</span>" : '';
    }
}