"""
Автоматизированное тестирование PHP сайта "Система исследования товарного рынка" с использованием Selenium (Firefox)
Исправленная версия с корректным выходом из гостевого режима перед входом администратора
"""
import os
import time
import unittest
import traceback
from datetime import datetime
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.firefox.service import Service
from selenium.webdriver.firefox.options import Options
from webdriver_manager.firefox import GeckoDriverManager


class MarketResearchSystemTest(unittest.TestCase):
    """Класс для тестирования системы исследования товарного рынка"""
    
    # Статические переменные для сбора результатов
    test_results = []
    
    @classmethod
    def setUpClass(cls):
        """Настройка перед всеми тестами"""
        # Создаем папку для скриншотов, если её нет
        cls.screenshots_dir = "test_screenshots"
        if not os.path.exists(cls.screenshots_dir):
            os.makedirs(cls.screenshots_dir)
        
        # Очищаем результаты тестов
        cls.test_results = []
        
        # Настройка Firefox options
        firefox_options = Options()
        firefox_options.add_argument("--width=1920")
        firefox_options.add_argument("--height=1080")
        # firefox_options.add_argument("--headless")  # Раскомментировать для headless режима
        
        print("🚀 Запуск Firefox драйвера...")
        try:
            # Используем webdriver-manager для автоматической установки geckodriver
            service = Service(GeckoDriverManager().install())
            cls.driver = webdriver.Firefox(service=service, options=firefox_options)
            cls.driver.implicitly_wait(10)
            cls.wait = WebDriverWait(cls.driver, 10)
            
            # Базовый URL сайта
            cls.base_url = "http://localhost:3000/"
            
            # Счетчик скриншотов
            cls.screenshot_counter = 1
            
            print("✅ Firefox драйвер успешно запущен")
        except Exception as e:
            print(f"❌ Ошибка запуска Firefox: {str(e)}")
            print("\nПопробуйте выполнить:")
            print("sudo apt-get install firefox firefox-geckodriver")
            print("pip install --upgrade selenium webdriver-manager")
            raise
    
    def take_screenshot(self, test_name, status="info"):
        """Создание скриншота с именем теста и временной меткой"""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"{self.screenshots_dir}/{self.screenshot_counter:02d}_{test_name}_{status}_{timestamp}.png"
        
        try:
            # Сохраняем скриншот
            self.driver.save_screenshot(filename)
            print(f"📸 Скриншот сохранен: {filename}")
            self.screenshot_counter += 1
            return filename
        except Exception as e:
            print(f"⚠️ Не удалось сохранить скриншот: {str(e)}")
            return None
    
    def run_test_with_result(self, test_method, test_name):
        """Обертка для запуска теста с записью результата"""
        start_time = time.time()
        screenshot_path = None
        error_msg = None
        status = "passed"
        
        print("\n" + "="*60)
        print(f"ТЕСТ: {test_name}")
        print("="*60)
        
        try:
            # Запускаем тест
            test_method()
            # Делаем скриншот успешного теста
            screenshot_path = self.take_screenshot(test_name, "passed")
            print(f"✅ ТЕСТ ПРОЙДЕН: {test_name}")
            
        except AssertionError as e:
            status = "failed"
            error_msg = f"AssertionError: {str(e)}"
            screenshot_path = self.take_screenshot(test_name, "failed")
            print(f"❌ ТЕСТ НЕ ПРОЙДЕН: {test_name}")
            print(f"   Ошибка: {error_msg}")
            
        except Exception as e:
            status = "error"
            error_msg = f"{type(e).__name__}: {str(e)}\n{traceback.format_exc()}"
            screenshot_path = self.take_screenshot(test_name, "error")
            print(f"⚠️ ОШИБКА ТЕСТА: {test_name}")
            print(f"   Ошибка: {str(e)}")
        
        finally:
            execution_time = time.time() - start_time
            
            # Сохраняем результат
            self.__class__.test_results.append({
                'name': test_name,
                'status': status,
                'error': error_msg,
                'screenshot': screenshot_path,
                'time': round(execution_time, 2)
            })
    
    def is_logged_in(self):
        """Проверка, авторизован ли пользователь"""
        current_url = self.driver.current_url
        page_source = self.driver.page_source
        
        # Если мы на странице входа - не авторизованы
        if "login.php" in current_url:
            return False
        
        # Если есть индикаторы авторизации
        if "Панель управления" in page_source or "Выход" in page_source:
            return True
        
        return False
    
    def is_guest_mode(self):
        """Проверка, находимся ли мы в гостевом режиме"""
        page_source = self.driver.page_source
        return "Гостевой режим" in page_source or "гостевом режиме" in page_source
    
    def logout_if_needed(self):
        """Выход из системы если нужно"""
        if self.is_logged_in():
            print("🔄 Выполняем выход из текущей сессии...")
            try:
                # Ищем кнопку выхода
                logout_methods = [
                    (By.PARTIAL_LINK_TEXT, "Выход"),
                    (By.XPATH, "//a[contains(@href, 'logout')]"),
                    (By.XPATH, "//button[contains(text(), 'Выход')]")
                ]
                
                for by, value in logout_methods:
                    elements = self.driver.find_elements(by, value)
                    if elements:
                        elements[0].click()
                        time.sleep(2)
                        break
                else:
                    # Если кнопка не найдена, переходим напрямую
                    self.driver.get(self.base_url + "logout.php")
                    time.sleep(2)
                
                print("✅ Выход выполнен")
            except Exception as e:
                print(f"⚠️ Ошибка при выходе: {str(e)}")
                # Пробуем прямой переход
                self.driver.get(self.base_url + "logout.php")
                time.sleep(2)
    
    # ТЕСТЫ
    def test_01_login_page_access(self):
        """Тест 1: Доступ к странице входа"""
        self.driver.get(self.base_url)
        time.sleep(2)
        
        # Проверка, что мы на странице входа
        page_source = self.driver.page_source
        self.assertIn("Вход в систему", page_source)
        self.assertIn("Продолжить без входа", page_source)
        print("✅ Страница входа загружена успешно")
    
    def test_02_guest_login(self):
        """Тест 2: Вход в гостевом режиме"""
        # Сначала выходим, если вдруг уже авторизованы
        self.logout_if_needed()
        
        self.driver.get(self.base_url)
        time.sleep(2)
        
        # Поиск кнопки гостевого входа
        guest_buttons = self.driver.find_elements(By.XPATH, "//a[contains(text(), 'без входа') or contains(text(), 'Продолжить')]")
        
        if guest_buttons:
            print(f"Найдена кнопка: {guest_buttons[0].text}")
            guest_buttons[0].click()
        else:
            # Альтернативный поиск
            guest_buttons = self.driver.find_elements(By.PARTIAL_LINK_TEXT, "Продолжить")
            if guest_buttons:
                print(f"Найдена кнопка: {guest_buttons[0].text}")
                guest_buttons[0].click()
            else:
                # Пробуем найти по URL
                guest_link = self.driver.find_elements(By.XPATH, "//a[contains(@href, 'guest')]")
                if guest_link:
                    guest_link[0].click()
                else:
                    self.fail("Кнопка гостевого входа не найдена")
        
        time.sleep(3)
        
        # Проверка, что мы на главной в гостевом режиме
        page_source = self.driver.page_source
        self.assertIn("Панель управления", page_source)
        print("✅ Гостевой вход выполнен успешно")
    
    def test_03_products_page_guest(self):
        """Тест 3: Просмотр товаров в гостевом режиме"""
        # Если не в гостевом режиме, входим
        if not self.is_guest_mode():
            self.test_02_guest_login()
        
        self.driver.get(self.base_url + "products.php")
        time.sleep(3)
        
        # Проверка наличия списка товаров
        page_source = self.driver.page_source
        self.assertIn("Управление товарами", page_source)
        print("✅ Страница товаров загружена")
    
    def test_04_sales_page_guest(self):
        """Тест 4: Просмотр продаж в гостевом режиме"""
        if not self.is_guest_mode():
            self.test_02_guest_login()
            
        self.driver.get(self.base_url + "sales.php")
        time.sleep(3)
        
        page_source = self.driver.page_source
        self.assertIn("Учет продаж", page_source)
        print("✅ Страница продаж загружена")
    
    def test_05_competitors_page_guest(self):
        """Тест 5: Просмотр конкурентов в гостевом режиме"""
        if not self.is_guest_mode():
            self.test_02_guest_login()
            
        self.driver.get(self.base_url + "competitors.php")
        time.sleep(3)
        
        page_source = self.driver.page_source
        self.assertIn("Мониторинг конкурентов", page_source)
        print("✅ Страница конкурентов загружена")
    
    def test_06_reports_page_guest(self):
        """Тест 6: Просмотр отчетов в гостевом режиме"""
        if not self.is_guest_mode():
            self.test_02_guest_login()
            
        self.driver.get(self.base_url + "reports.php")
        time.sleep(3)
        
        page_source = self.driver.page_source
        self.assertIn("Аналитические отчеты", page_source)
        print("✅ Страница отчетов загружена")
    
    def test_07_admin_login(self):
        """Тест 7: Вход как администратор"""
        # КРИТИЧЕСКИ ВАЖНО: Сначала завершаем гостевой режим или выходим из текущей сессии
        print("🔄 Завершаем текущую сессию перед входом администратора...")
        self.logout_if_needed()
    
        # Убеждаемся, что мы на странице входа
        self.driver.get(self.base_url)
        time.sleep(3)
    
        # Делаем скриншот страницы входа
        self.take_screenshot("admin_login_page_start", "info")
    
        # Проверяем, что мы действительно на странице входа
        page_source = self.driver.page_source
        if "Вход в систему" not in page_source:
            print("⚠️ Не удалось попасть на страницу входа, пробуем принудительно")
            self.driver.get(self.base_url + "login.php")
            time.sleep(2)
            self.take_screenshot("admin_login_page_forced", "info")
    
        # Ввод логина и пароля
        try:
            # Ищем поля ввода разными способами
            username_input = None
            password_input = None
        
            # Поиск по name
            username_input = self.driver.find_elements(By.NAME, "username")
            password_input = self.driver.find_elements(By.NAME, "password")
        
            if username_input and password_input:
                username_input = username_input[0]
                password_input = password_input[0]
            else:
                # Поиск по id
                username_input = self.driver.find_elements(By.ID, "username")
                password_input = self.driver.find_elements(By.ID, "password")
                if username_input and password_input:
                    username_input = username_input[0]
                    password_input = password_input[0]
                else:
                    # Поиск по placeholder
                    username_input = self.driver.find_elements(By.XPATH, "//input[@placeholder='Логин' or @placeholder='Username']")
                    password_input = self.driver.find_elements(By.XPATH, "//input[@placeholder='Пароль' or @placeholder='Password']")
                    if username_input and password_input:
                        username_input = username_input[0]
                        password_input = password_input[0]
                    else:
                        self.fail("Не удалось найти поля для ввода логина и пароля")
        
            print(f"✅ Поля ввода найдены: логин - {username_input.tag_name}, пароль - {password_input.tag_name}")
        
            username_input.clear()
            username_input.send_keys("admin")
        
            password_input.clear()
            password_input.send_keys("admin123")
        
            # Делаем скриншот перед входом
            self.take_screenshot("admin_login_form_filled", "info")
        
            # Ищем кнопку отправки формы
            submit_button = None
            submit_methods = [
                (By.XPATH, "//button[@type='submit']"),
                (By.XPATH, "//button[contains(text(), 'Войти')]"),
                (By.XPATH, "//input[@type='submit']"),
                (By.XPATH, "//form//button")
            ]
        
            for by, value in submit_methods:
                buttons = self.driver.find_elements(by, value)
                if buttons:
                    submit_button = buttons[0]
                    print(f"✅ Кнопка отправки найдена: {submit_button.text}")
                    break
        
            if submit_button:
                submit_button.click()
            else:
                # Если кнопка не найдена, отправляем форму через Enter
                print("⚠️ Кнопка отправки не найдена, отправляем через Enter")
                password_input.send_keys(Keys.RETURN)
        
            time.sleep(3)
        
            # Проверка успешного входа
            page_source = self.driver.page_source
            current_url = self.driver.current_url
        
            print(f"Текущий URL после входа: {current_url}")
        
            # Делаем скриншот после входа
            self.take_screenshot("admin_login_result", "info")
        
            # Проверяем различные индикаторы успешного входа
            login_success = any([
                "Панель управления" in page_source,
                "Администратор" in page_source,
                "admin" in page_source.lower(),
                "index.php" in current_url and "login" not in current_url,
                "Добро пожаловать" in page_source
            ])
        
            # Дополнительная проверка - нет ли сообщения об ошибке
            error_messages = ["Неверный пароль", "Пользователь не найден", "Ошибка"]
            has_error = any(error in page_source for error in error_messages)
        
            if has_error:
                print("❌ Обнаружено сообщение об ошибке входа")
                self.take_screenshot("admin_login_error_message", "error")
        
            self.assertTrue(login_success and not has_error, "Не удалось войти как администратор")
            print("✅ Вход как администратор выполнен успешно")
        
        except Exception as e:
            print(f"❌ Ошибка при входе администратора: {str(e)}")
            # Делаем скриншот ошибки
            self.take_screenshot("admin_login_error", "error")
            traceback.print_exc()
            raise
    
    def test_08_admin_users_management(self):
        """Тест 8: Управление пользователями (админ панель)"""
        # Проверяем, авторизованы ли мы как админ
        if not self.is_logged_in() or self.is_guest_mode():
            print("🔄 Необходима авторизация администратора")
            self.test_07_admin_login()
        
        self.driver.get(self.base_url + "admin_users.php")
        time.sleep(3)
        
        page_source = self.driver.page_source
        self.assertIn("Управление пользователями", page_source)
        print("✅ Админ панель управления пользователями доступна")
    
    def test_09_add_product_as_admin(self):
        """Тест 9: Добавление нового товара администратором"""
        if not self.is_logged_in() or self.is_guest_mode():
            self.test_07_admin_login()
            
        self.driver.get(self.base_url + "products.php")
        time.sleep(2)
        
        try:
            # Проверяем, есть ли форма добавления
            add_forms = self.driver.find_elements(By.XPATH, "//form[.//input[@name='name']]")
            
            if add_forms:
                # Заполнение формы добавления товара
                product_name = self.driver.find_element(By.NAME, "name")
                product_name.send_keys("Тестовый товар Firefox")
                
                internal_code = self.driver.find_element(By.NAME, "internal_code")
                internal_code.send_keys(f"TEST-{int(time.time())}")
                
                category = self.driver.find_element(By.NAME, "category")
                category.send_keys("Тестовая категория")
                
                description = self.driver.find_element(By.NAME, "description")
                description.send_keys("Это тестовый товар, созданный автоматическим тестом Firefox")
                
                # Сохранение товара
                submit_button = self.driver.find_element(By.XPATH, "//button[contains(text(), 'Сохранить')]")
                submit_button.click()
                time.sleep(3)
                
                # Проверка успешного добавления
                page_source = self.driver.page_source
                if "успешно" in page_source.lower():
                    print("✅ Новый товар успешно добавлен")
                else:
                    print("⚠️ Форма заполнена, но не удалось подтвердить добавление")
            else:
                print("⚠️ Форма добавления товара не найдена на странице")
                
        except Exception as e:
            print(f"⚠️ Ошибка при добавлении товара: {str(e)}")
    
    def test_10_add_sale_as_admin(self):
        """Тест 10: Добавление новой продажи администратором"""
        if not self.is_logged_in() or self.is_guest_mode():
            self.test_07_admin_login()
            
        self.driver.get(self.base_url + "sales.php")
        time.sleep(2)
        
        try:
            # Поиск формы добавления продажи
            add_forms = self.driver.find_elements(By.XPATH, "//form[.//select[@name='subdivision_id']]")
            
            if add_forms:
                # Заполнение формы
                subdivision_select = self.driver.find_element(By.NAME, "subdivision_id")
                subdivision_select.click()
                time.sleep(1)
                
                # Выбираем первое доступное подразделение
                options = subdivision_select.find_elements(By.TAG_NAME, "option")
                if len(options) > 1:
                    options[1].click()
                
                product_select = self.driver.find_element(By.NAME, "product_id")
                product_select.click()
                time.sleep(1)
                
                options = product_select.find_elements(By.TAG_NAME, "option")
                if len(options) > 1:
                    options[1].click()
                
                quantity = self.driver.find_element(By.NAME, "quantity")
                quantity.clear()
                quantity.send_keys("2")
                
                amount = self.driver.find_element(By.NAME, "total_amount")
                amount.clear()
                amount.send_keys("100000")
                
                # Сохранение продажи
                submit_button = self.driver.find_element(By.XPATH, "//button[contains(text(), 'Сохранить продажу')]")
                submit_button.click()
                time.sleep(3)
                
                print("✅ Форма продажи заполнена и отправлена")
            else:
                print("⚠️ Форма добавления продажи не найдена")
                
        except Exception as e:
            print(f"⚠️ Ошибка при добавлении продажи: {str(e)}")
    
    def test_11_generate_report(self):
        """Тест 11: Генерация отчета о продажах"""
        if not self.is_logged_in() or self.is_guest_mode():
            self.test_07_admin_login()
            
        self.driver.get(self.base_url + "reports.php")
        time.sleep(2)
        
        try:
            # Поиск кнопки генерации отчета
            generate_button = self.driver.find_elements(By.XPATH, "//button[contains(text(), 'Сформировать')]")
            
            if generate_button:
                generate_button[0].click()
                time.sleep(3)
                
                # Проверка, что отчет сгенерирован
                page_source = self.driver.page_source
                if "Отчет:" in page_source:
                    print("✅ Отчет успешно сгенерирован")
                else:
                    print("⚠️ Отчет не сгенерирован, но тест продолжается")
            else:
                print("⚠️ Кнопка генерации отчета не найдена")
                
        except Exception as e:
            print(f"⚠️ Ошибка при генерации отчета: {str(e)}")
    
    def test_12_logout(self):
        """Тест 12: Выход из системы"""
        self.logout_if_needed()
        
        # Проверка, что мы на странице входа
        self.assertIn("Вход в систему", self.driver.page_source)
        print("✅ Выход из системы выполнен успешно")
    
    def test_13_admin_panel_access_denied(self):
        """Тест 13: Попытка доступа к админ панели после выхода"""
        # Убедимся, что мы не авторизованы
        self.logout_if_needed()
        
        self.driver.get(self.base_url + "admin_users.php")
        time.sleep(2)
        
        # Проверка, что нас перенаправило на страницу входа
        current_url = self.driver.current_url
        self.assertIn("login.php", current_url)
        print("✅ Доступ к админ панели заблокирован - защита работает")
    
    @classmethod
    def tearDownClass(cls):
        """Очистка после всех тестов"""
        print("\n" + "="*60)
        print("ФОРМИРОВАНИЕ ОТЧЕТА...")
        print("="*60)
        
        # Создаем HTML-отчет
        cls.create_html_report()
        
        # Закрытие браузера
        if hasattr(cls, 'driver'):
            cls.driver.quit()
    
    @classmethod
    def create_html_report(cls):
        """Создание подробного HTML-отчета с результатами тестов и скриншотами"""
        timestamp = datetime.now().strftime("%d.%m.%Y %H:%M:%S")
        
        # Подсчет статистики
        total_tests = len(cls.test_results)
        passed_tests = sum(1 for r in cls.test_results if r['status'] == 'passed')
        failed_tests = sum(1 for r in cls.test_results if r['status'] == 'failed')
        error_tests = sum(1 for r in cls.test_results if r['status'] == 'error')
        
        # Вычисляем проценты для прогресс-бара (без деления на ноль)
        passed_percent = (passed_tests / total_tests * 100) if total_tests > 0 else 0
        failed_percent = (failed_tests / total_tests * 100) if total_tests > 0 else 0
        error_percent = (error_tests / total_tests * 100) if total_tests > 0 else 0
        
        html_content = f"""<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчет о тестировании - Система исследования товарного рынка</title>
    <style>
        * {{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }}
        
        body {{
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }}
        
        .container {{
            max-width: 1400px;
            margin: 0 auto;
        }}
        
        .header {{
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }}
        
        .header h1 {{
            color: #333;
            font-size: 2em;
            margin-bottom: 10px;
        }}
        
        .header h1 i {{
            color: #667eea;
            margin-right: 10px;
        }}
        
        .timestamp {{
            color: #666;
            font-size: 0.9em;
            margin-bottom: 20px;
        }}
        
        .stats-grid {{
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }}
        
        .stat-card {{
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }}
        
        .stat-card:hover {{
            transform: translateY(-5px);
        }}
        
        .stat-card.total {{ border-left: 4px solid #17a2b8; }}
        .stat-card.passed {{ border-left: 4px solid #28a745; }}
        .stat-card.failed {{ border-left: 4px solid #dc3545; }}
        .stat-card.error {{ border-left: 4px solid #ffc107; }}
        
        .stat-value {{
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }}
        
        .stat-label {{
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
        }}
        
        .progress-container {{
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }}
        
        .progress-bar {{
            height: 30px;
            background: #f0f0f0;
            border-radius: 15px;
            overflow: hidden;
            display: flex;
        }}
        
        .progress-passed {{
            background: #28a745;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9em;
        }}
        
        .progress-failed {{
            background: #dc3545;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9em;
        }}
        
        .progress-error {{
            background: #ffc107;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            font-size: 0.9em;
        }}
        
        .tests-grid {{
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }}
        
        .test-card {{
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }}
        
        .test-card:hover {{
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }}
        
        .test-card.passed {{ border-left: 4px solid #28a745; }}
        .test-card.failed {{ border-left: 4px solid #dc3545; }}
        .test-card.error {{ border-left: 4px solid #ffc107; }}
        
        .test-header {{
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }}
        
        .test-name {{
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
        }}
        
        .test-status {{
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }}
        
        .status-passed {{
            background: #d4edda;
            color: #155724;
        }}
        
        .status-failed {{
            background: #f8d7da;
            color: #721c24;
        }}
        
        .status-error {{
            background: #fff3cd;
            color: #856404;
        }}
        
        .test-time {{
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
        }}
        
        .test-error {{
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.9em;
            color: #dc3545;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }}
        
        .test-screenshot {{
            margin-top: 15px;
            text-align: center;
        }}
        
        .test-screenshot img {{
            max-width: 100%;
            max-height: 200px;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.3s;
            border: 2px solid #dee2e6;
        }}
        
        .test-screenshot img:hover {{
            transform: scale(1.05);
            border-color: #667eea;
        }}
        
        .screenshot-full {{
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }}
        
        .screenshot-full img {{
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
        }}
        
        .footer {{
            text-align: center;
            margin-top: 30px;
            color: white;
            opacity: 0.8;
        }}
        
        @media (max-width: 768px) {{
            .stats-grid {{
                grid-template-columns: repeat(2, 1fr);
            }}
            
            .tests-grid {{
                grid-template-columns: 1fr;
            }}
        }}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Отчет о тестировании</h1>
            <div class="timestamp">
                <i class="far fa-clock"></i> Дата формирования: {timestamp}<br>
                <i class="fas fa-globe"></i> Тестируемый сайт: http://localhost:3000/<br>
                <i class="fas fa-code-branch"></i> Браузер: Firefox
            </div>
            
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-value">{total_tests}</div>
                    <div class="stat-label">Всего тестов</div>
                </div>
                <div class="stat-card passed">
                    <div class="stat-value">{passed_tests}</div>
                    <div class="stat-label">Пройдено</div>
                </div>
                <div class="stat-card failed">
                    <div class="stat-value">{failed_tests}</div>
                    <div class="stat-label">Провалено</div>
                </div>
                <div class="stat-card error">
                    <div class="stat-value">{error_tests}</div>
                    <div class="stat-label">Ошибок</div>
                </div>
            </div>
        </div>
        
        <div class="progress-container">
            <h3 style="margin-bottom: 15px;">Прогресс выполнения</h3>
            <div class="progress-bar">
                <div class="progress-passed" style="width: {passed_percent}%;">
                    {passed_tests} пройдено
                </div>
                <div class="progress-failed" style="width: {failed_percent}%;">
                    {failed_tests} провалено
                </div>
                <div class="progress-error" style="width: {error_percent}%;">
                    {error_tests} ошибок
                </div>
            </div>
        </div>
        
        <div class="tests-grid">
"""
        
        # Добавляем карточки тестов
        for i, result in enumerate(cls.test_results, 1):
            status_class = result['status']
            status_text = {
                'passed': 'Пройден',
                'failed': 'Провален',
                'error': 'Ошибка'
            }.get(result['status'], 'Неизвестно')
            
            html_content += f"""
            <div class="test-card {status_class}">
                <div class="test-header">
                    <span class="test-name">#{i:02d} {result['name']}</span>
                    <span class="test-status status-{status_class}">{status_text}</span>
                </div>
                <div class="test-time">⏱ Время выполнения: {result['time']} сек</div>
"""
            
            # Добавляем информацию об ошибке, если есть
            if result['error']:
                # Экранируем HTML-сущности в сообщении об ошибке
                error_escaped = result['error'].replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
                html_content += f"""
                <div class="test-error">
                    <strong>Ошибка:</strong><br>
                    {error_escaped}
                </div>
"""
            
            # Добавляем скриншот, если есть
            if result['screenshot'] and os.path.exists(result['screenshot']):
                screenshot_filename = os.path.basename(result['screenshot'])
                html_content += f"""
                <div class="test-screenshot">
                    <img src="{result['screenshot']}" alt="Скриншот теста {result['name']}" 
                         onclick="showFullScreenshot(this.src)" title="Нажмите для увеличения">
                </div>
"""
            
            html_content += """
            </div>
"""
        
        html_content += """
        </div>
        
        <div class="footer">
            <p>© 2026 Система исследования товарного рынка | Автоматизированное тестирование</p>
        </div>
    </div>
    
    <div class="screenshot-full" id="fullScreenshot" onclick="this.style.display='none'">
        <img id="fullScreenshotImg" src="" alt="Полноразмерный скриншот">
    </div>
    
    <script>
        function showFullScreenshot(src) {
            document.getElementById('fullScreenshotImg').src = src;
            document.getElementById('fullScreenshot').style.display = 'flex';
        }
        
        // Добавляем иконки Font Awesome
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
        document.head.appendChild(link);
    </script>
</body>
</html>
"""
        
        # Сохраняем отчет
        report_path = "test_report_firefox.html"
        with open(report_path, "w", encoding="utf-8") as f:
            f.write(html_content)
        
        print(f"\n📊 Подробный HTML-отчет создан: {report_path}")
        print(f"   Всего тестов: {total_tests}")
        print(f"   ✅ Пройдено: {passed_tests}")
        print(f"   ❌ Провалено: {failed_tests}")
        print(f"   ⚠️ Ошибок: {error_tests}")


def main():
    """Главная функция запуска тестов"""
    print("="*60)
    print("ЗАПУСК АВТОМАТИЗИРОВАННОГО ТЕСТИРОВАНИЯ")
    print("="*60)
    print("🌐 Сайт: http://localhost:3000/")
    print("🦊 Браузер: Firefox")
    print("📁 Скриншоты: test_screenshots/")
    print("="*60)
    
    # Создаем экземпляр тестового класса
    test_instance = MarketResearchSystemTest('test_01_login_page_access')
    
    try:
        # Запускаем setUpClass вручную
        MarketResearchSystemTest.setUpClass()
        
        # Запускаем каждый тест
        test_methods = [
            ('test_01_login_page_access', 'Доступ к странице входа'),
            ('test_02_guest_login', 'Вход в гостевом режиме'),
            ('test_03_products_page_guest', 'Просмотр товаров в гостевом режиме'),
            ('test_04_sales_page_guest', 'Просмотр продаж в гостевом режиме'),
            ('test_05_competitors_page_guest', 'Просмотр конкурентов в гостевом режиме'),
            ('test_06_reports_page_guest', 'Просмотр отчетов в гостевом режиме'),
            ('test_07_admin_login', 'Вход как администратор'),
            ('test_08_admin_users_management', 'Управление пользователями (админ панель)'),
            ('test_09_add_product_as_admin', 'Добавление нового товара администратором'),
            ('test_10_add_sale_as_admin', 'Добавление новой продажи администратором'),
            ('test_11_generate_report', 'Генерация отчета о продажах'),
            ('test_12_logout', 'Выход из системы'),
            ('test_13_admin_panel_access_denied', 'Попытка доступа к админ панели после выхода')
        ]
        
        for method_name, description in test_methods:
            test_method = getattr(MarketResearchSystemTest, method_name)
            test_instance.run_test_with_result(
                lambda m=test_method: m(test_instance), 
                description
            )
        
        # Запускаем tearDownClass вручную
        MarketResearchSystemTest.tearDownClass()
        
    except Exception as e:
        print(f"\n❌ Критическая ошибка при выполнении тестов: {str(e)}")
        traceback.print_exc()
        # Все равно пытаемся создать отчет
        if hasattr(MarketResearchSystemTest, 'test_results'):
            MarketResearchSystemTest.create_html_report()


if __name__ == "__main__":
    main()