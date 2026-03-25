#!/bin/bash
# ЗАПУСТИТЬ_ТЕСТЫ.sh - Автоматический запуск Selenium и тестов
# Для запуска выполните: ./ЗАПУСТИТЬ_ТЕСТЫ.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SELENIUM_JAR="$SCRIPT_DIR/selenium-server.jar"
SELENIUM_PORT=4444
SELENIUM_PID=""

echo "========================================"
echo "  Запуск тестирования веб-приложения"
echo "========================================"
echo ""

# Проверяем наличие Selenium Server
if [ ! -f "$SELENIUM_JAR" ]; then
    echo "✗ Selenium Server не найден: $SELENIUM_JAR"
    echo "Скачивание..."
    curl -L -o "$SELENIUM_JAR" https://github.com/SeleniumHQ/selenium/releases/download/selenium-4.27.0/selenium-server-4.27.0.jar
fi

# Проверяем наличие Composer зависимостей
if [ ! -d "$SCRIPT_DIR/vendor" ] || [ ! -f "$SCRIPT_DIR/vendor/autoload.php" ]; then
    echo "Composer зависимости не найдены"
    echo "Установка зависимостей..."
    
    # Проверяем наличие composer.phar, если нет — скачиваем
    COMPOSER_PHAR="$SCRIPT_DIR/composer.phar"
    if [ ! -f "$COMPOSER_PHAR" ]; then
        echo "Скачивание Composer..."
        curl -sS https://getcomposer.org/installer | php -- --install-dir="$SCRIPT_DIR" --filename=composer.phar
    fi
    
    # Устанавливаем зависимости
    php "$COMPOSER_PHAR" install --working-dir="$SCRIPT_DIR" --quiet
    if [ $? -eq 0 ]; then
        echo "✓ Зависимости установлены"
    else
        echo "✗ Ошибка установки зависимостей"
        exit 1
    fi
fi

# Проверяем, не запущен ли уже Selenium
if curl -s http://localhost:$SELENIUM_PORT/status > /dev/null 2>&1; then
    echo "✓ Selenium Server уже запущен на порту $SELENIUM_PORT"
else
    echo "Запуск Selenium Server..."
    java -jar "$SELENIUM_JAR" standalone --port $SELENIUM_PORT > "$SCRIPT_DIR/selenium.log" 2>&1 &
    SELENIUM_PID=$!
    echo "  PID Selenium: $SELENIUM_PID"

    # Ждём запуска Selenium (до 30 секунд)
    echo "Ожидание запуска Selenium..."
    for i in {1..30}; do
        if curl -s http://localhost:$SELENIUM_PORT/status > /dev/null 2>&1; then
            echo "✓ Selenium Server запущен"
            break
        fi
        if [ $i -eq 30 ]; then
            echo "✗ Превышено время ожидания запуска Selenium"
            cat "$SCRIPT_DIR/selenium.log"
            exit 1
        fi
        sleep 1
    done
fi

echo ""
echo "Запуск тестов..."
echo ""

# Запускаем тесты
php "$SCRIPT_DIR/web_tests.php"
TEST_EXIT_CODE=$?

# Если мы запускали Selenium, останавливаем его
if [ -n "$SELENIUM_PID" ] && kill -0 $SELENIUM_PID 2>/dev/null; then
    echo ""
    echo "Остановка Selenium Server..."
    kill $SELENIUM_PID 2>/dev/null
    wait $SELENIUM_PID 2>/dev/null
    echo "✓ Selenium Server остановлен"
fi

echo ""
echo "========================================"
echo "  Тестирование завершено"
echo "========================================"
echo ""
echo "Отчёт: $SCRIPT_DIR/test_report.md"

exit $TEST_EXIT_CODE
