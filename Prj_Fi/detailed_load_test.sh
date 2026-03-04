#!/bin/bash
# detailed_load_test.sh - создаёт отчёт в PDF с поддержкой русского языка

URL="http://localhost:3000/index.php?page=home"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_NAME="load_test_detailed_$TIMESTAMP"
TXT_REPORT="$REPORT_NAME.txt"
PDF_REPORT="$REPORT_NAME.pdf"

echo "Запуск тестирования. Ожидайте..."
TEMP_FILE=$(mktemp)

# Запуск теста
ab -n 1000 -c 100 "$URL" > "$TEMP_FILE" 2>&1

# Извлечение ключевых метрик
REQUESTS_PER_SECOND=$(grep "Requests per second" "$TEMP_FILE" | awk '{print $4}')
TIME_PER_REQUEST=$(grep "Time per request.*mean" "$TEMP_FILE" | head -1 | awk '{print $4}')
FAILED_REQUESTS=$(grep "Failed requests" "$TEMP_FILE" | awk '{print $3}')
TRANSFER_RATE=$(grep "Transfer rate" "$TEMP_FILE" | awk '{print $3}')

# Извлечение дополнительных метрик
COMPLETE_REQUESTS=$(grep "Complete requests" "$TEMP_FILE" | awk '{print $3}')
TOTAL_TRANSFERRED=$(grep "Total transferred" "$TEMP_FILE" | awk '{print $3 " " $4}')
HTML_TRANSFERRED=$(grep "HTML transferred" "$TEMP_FILE" | awk '{print $3 " " $4}')
CONCURRENCY_LEVEL=$(grep "Concurrency Level" "$TEMP_FILE" | awk '{print $3}')
TIME_TAKEN=$(grep "Time taken for tests" "$TEMP_FILE" | awk '{print $5 " " $6}')

# Очистка вывода ab от спецсимволов
CLEAN_AB_OUTPUT=$(cat "$TEMP_FILE" | sed 's/\x1b\[[0-9;]*m//g' | sed 's/\r//g')

# Установка необходимых пакетов для работы с PDF
if ! command -v pandoc &> /dev/null; then
    echo "Устанавливаем pandoc для конвертации в PDF..."
    sudo apt-get update
    sudo apt-get install -y pandoc texlive-xetex texlive-fonts-recommended texlive-fonts-extra
fi

# Создание Markdown файла с русским текстом
MARKDOWN_REPORT="$REPORT_NAME.md"

cat > "$MARKDOWN_REPORT" << 'EOF'

EOF

# Добавляем содержимое с подстановкой переменных
cat >> "$MARKDOWN_REPORT" << EOF
**Дата и время теста:** $(date)

**Тестируемый URL:** $URL

**Параметры теста:** -n 1000 (запросов), -c 100 (одновременных пользователей)

---

## КЛЮЧЕВЫЕ ПОКАЗАТЕЛИ ПРОИЗВОДИТЕЛЬНОСТИ

| Показатель | Значение |
|------------|----------|
| Запросов в секунду (RPS) | ${REQUESTS_PER_SECOND:-0} req/сек |
| Среднее время ответа | ${TIME_PER_REQUEST:-0} мс |
| Неудачных запросов | ${FAILED_REQUESTS:-0} |
| Скорость передачи данных | ${TRANSFER_RATE:-0} KB/сек |

---

## ДОПОЛНИТЕЛЬНАЯ ИНФОРМАЦИЯ

| Параметр | Значение |
|----------|----------|
| Всего запросов | $COMPLETE_REQUESTS |
| Уровень конкурентности | $CONCURRENCY_LEVEL |
| Время выполнения теста | $TIME_TAKEN |
| Всего передано данных | $TOTAL_TRANSFERRED |
| Передано HTML данных | $HTML_TRANSFERRED |

---

## АНАЛИЗ РЕЗУЛЬТАТОВ

### Анализ времени ответа:

EOF

# Добавляем анализ времени ответа
if [[ -n "$TIME_PER_REQUEST" && "$TIME_PER_REQUEST" != "0" ]]; then
    if (( $(echo "$TIME_PER_REQUEST < 100" | bc -l 2>/dev/null) )); then
        echo "✅ **ОТЛИЧНО:** Время ответа менее 100 мс. Сервер отлично справляется с нагрузкой." >> "$MARKDOWN_REPORT"
    elif (( $(echo "$TIME_PER_REQUEST < 300" | bc -l 2>/dev/null) )); then
        echo "👍 **ХОРОШО:** Время ответа 100-300 мс. Приемлемое время ответа для веб-приложения." >> "$MARKDOWN_REPORT"
    elif (( $(echo "$TIME_PER_REQUEST < 500" | bc -l 2>/dev/null) )); then
        echo "⚠️ **СРЕДНЕЕ:** Время ответа 300-500 мс. Рекомендуется оптимизация." >> "$MARKDOWN_REPORT"
    else
        echo "❌ **ПЛОХО:** Время ответа более 500 мс. Требуется срочная оптимизация." >> "$MARKDOWN_REPORT"
    fi
fi

cat >> "$MARKDOWN_REPORT" << EOF

### Анализ успешности запросов:

EOF

# Добавляем анализ успешности
if [[ -n "$FAILED_REQUESTS" && "$FAILED_REQUESTS" -gt 0 ]]; then
    ERROR_PERCENT=$(echo "scale=2; $FAILED_REQUESTS * 100 / 1000" | bc)
    echo "❌ **ОБНАРУЖЕНЫ ОШИБКИ:** Неудачных запросов: $FAILED_REQUESST ($ERROR_PERCENT%)" >> "$MARKDOWN_REPORT"
else
    echo "✅ **ВСЕ ЗАПРОСЫ УСПЕШНЫ:** Процент ошибок: 0%" >> "$MARKDOWN_REPORT"
fi

cat >> "$MARKDOWN_REPORT" << EOF

### Анализ пропускной способности:

EOF

# Добавляем анализ пропускной способности
if (( $(echo "${REQUESTS_PER_SECOND:-0} < 50" | bc -l 2>/dev/null) )); then
    echo "🟡 **НИЗКАЯ:** Пропускная способность: ${REQUESTS_PER_SECOND:-0} запросов/сек" >> "$MARKDOWN_REPORT"
elif (( $(echo "${REQUESTS_PER_SECOND:-0} < 200" | bc -l 2>/dev/null) )); then
    echo "🟢 **СРЕДНЯЯ:** Пропускная способность: ${REQUESTS_PER_SECOND:-0} запросов/сек" >> "$MARKDOWN_REPORT"
else
    echo "💪 **ВЫСОКАЯ:** Пропускная способность: ${REQUESTS_PER_SECOND:-0} запросов/сек" >> "$MARKDOWN_REPORT"
fi

cat >> "$MARKDOWN_REPORT" << EOF

---

## РЕКОМЕНДАЦИИ ПО ОПТИМИЗАЦИИ

EOF

# Добавляем рекомендации
RECOMMENDATION_COUNT=0

if (( $(echo "$TIME_PER_REQUEST > 500" | bc -l 2>/dev/null) )); then
    RECOMMENDATION_COUNT=$((RECOMMENDATION_COUNT + 1))
    echo "$RECOMMENDATION_COUNT. 🔴 **Время ответа превышает 500 мс. Рекомендуется:**" >> "$MARKDOWN_REPORT"
    echo "   - Включить кэширование на уровне сервера" >> "$MARKDOWN_REPORT"
    echo "   - Оптимизировать запросы к базе данных" >> "$MARKDOWN_REPORT"
    echo "   - Увеличить лимиты соединений в веб-сервере" >> "$MARKDOWN_REPORT"
    echo "   - Рассмотреть возможность использования CDN" >> "$MARKDOWN_REPORT"
    echo "" >> "$MARKDOWN_REPORT"
fi

if [[ "$FAILED_REQUESTS" -gt 0 ]]; then
    RECOMMENDATION_COUNT=$((RECOMMENDATION_COUNT + 1))
    echo "$RECOMMENDATION_COUNT. 🔴 **Обнаружены ошибки. Рекомендуется:**" >> "$MARKDOWN_REPORT"
    echo "   - Проверить логи веб-сервера (error.log)" >> "$MARKDOWN_REPORT"
    echo "   - Увеличить максимальное количество соединений" >> "$MARKDOWN_REPORT"
    echo "   - Проверить настройки таймаутов" >> "$MARKDOWN_REPORT"
    echo "   - Протестировать при меньшей нагрузке" >> "$MARKDOWN_REPORT"
    echo "" >> "$MARKDOWN_REPORT"
fi

if (( $(echo "${REQUESTS_PER_SECOND:-0} < 50" | bc -l 2>/dev/null) )); then
    RECOMMENDATION_COUNT=$((RECOMMENDATION_COUNT + 1))
    echo "$RECOMMENDATION_COUNT. 🟡 **Низкая пропускная способность. Рекомендуется:**" >> "$MARKDOWN_REPORT"
    echo "   - Масштабировать сервер (больше CPU/RAM)" >> "$MARKDOWN_REPORT"
    echo "   - Использовать балансировку нагрузки" >> "$MARKDOWN_REPORT"
    echo "   - Оптимизировать код приложения" >> "$MARKDOWN_REPORT"
    echo "" >> "$MARKDOWN_REPORT"
fi

if [ $RECOMMENDATION_COUNT -eq 0 ]; then
    echo "✅ **Все показатели в норме.**" >> "$MARKDOWN_REPORT"
    echo "✅ Дополнительная оптимизация не требуется." >> "$MARKDOWN_REPORT"
    echo "✅ Система успешно справляется с нагрузкой в 100 одновременных пользователей." >> "$MARKDOWN_REPORT"
    echo "" >> "$MARKDOWN_REPORT"
fi

cat >> "$MARKDOWN_REPORT" << EOF
---

## СИСТЕМНАЯ ИНФОРМАЦИЯ

| Параметр | Значение |
|----------|----------|
| Имя хоста | $(hostname) |
| Ядер CPU | $(nproc) |
| ОЗУ всего | $(free -h | grep Mem | awk '{print $2}') |
| ОЗУ доступно | $(free -h | grep Mem | awk '{print $7}') |
| Версия ядра | $(uname -r) |

---

## ПОЛНЫЙ ВЫВОД УТИЛИТЫ AB

\`\`\`
$CLEAN_AB_OUTPUT
\`\`\`

---

*Отчёт сгенерирован автоматически $(date)*
EOF

# Конвертация Markdown в PDF через pandoc с поддержкой русского языка
echo "Конвертация в PDF через pandoc..."

# Создаём временный файл с заменой даты
sed -i "s/{{DATE}}/$(date)/g" "$MARKDOWN_REPORT"

# Конвертируем в PDF
pandoc "$MARKDOWN_REPORT" \
    --pdf-engine=xelatex \
    -V mainfont="DejaVu Sans" \
    -V monofont="DejaVu Sans Mono" \
    -V fontsize=11pt \
    -V geometry:"a4paper, left=2cm, right=2cm, top=2cm, bottom=2cm" \
    -o "$PDF_REPORT"

# Проверка создания PDF
if [ -f "$PDF_REPORT" ] && [ -s "$PDF_REPORT" ]; then
    echo "✅ PDF отчёт успешно создан: $PDF_REPORT"
    echo "   Размер файла: $(du -h "$PDF_REPORT" | cut -f1)"
    
    # Удаление временных файлов
    rm "$TEMP_FILE" "$MARKDOWN_REPORT" 2>/dev/null
    rm "$TXT_REPORT" 2>/dev/null
else
    echo "⚠️ Ошибка создания PDF. Создаём текстовый отчёт..."
    
    # Создаём текстовый отчёт как запасной вариант
    {
        echo "================================================================="
        echo "           ДЕТАЛЬНЫЙ ОТЧЁТ НАГРУЗОЧНОГО ТЕСТИРОВАНИЯ"
        echo "================================================================="
        echo "Дата и время:        $(date)"
        echo "URL:                 $URL"
        echo "Параметры:           -n 1000, -c 100"
        echo "================================================================="
        echo ""
        echo "Запросов в секунду:  ${REQUESTS_PER_SECOND:-0}"
        echo "Время на запрос:     ${TIME_PER_REQUEST:-0} мс"
        echo "Неудачных запросов:  ${FAILED_REQUESTS:-0}"
        echo "================================================================="
        echo ""
        echo "$CLEAN_AB_OUTPUT"
    } > "$TXT_REPORT"
    
    echo "📄 Текстовый отчёт: $TXT_REPORT"
fi

echo ""
echo "===================================================================================================="
echo "✅ ТЕСТИРОВАНИЕ УСПЕШНО ЗАВЕРШЕНО"
echo "===================================================================================================="
echo ""
if [ -f "$PDF_REPORT" ]; then
    echo "📊 PDF отчёт:       $PDF_REPORT"
else
    echo "📄 Текстовый отчёт: $TXT_REPORT"
fi
echo ""
echo "КРАТКИЕ РЕЗУЛЬТАТЫ:"
echo "----------------------------------------------------------------------------------------------------"
echo "📈 Запросов в секунду:  ${REQUESTS_PER_SECOND:-0}"
echo "⏱️  Время ответа:       ${TIME_PER_REQUEST:-0} мс"
echo "❌ Ошибок:              ${FAILED_REQUESTS:-0}"
echo "💾 Скорость передачи:   ${TRANSFER_RATE:-0} KB/сек"
echo "===================================================================================================="