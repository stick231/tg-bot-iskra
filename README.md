# tg‑bot‑iskra

A self‑hosted Telegram bot for task management: create tasks, set reminders, and track progress on your own server—no third‑party services required.

*This README first provides instructions in English. Русская версия ниже.*

---

## Requirements

- **PHP** 8.1 or higher  
- **Composer**  
- **MySQL** or **MariaDB**  
- **Telegram Bot Token**  
- Copy `.env.example` → `.env` and fill in credentials

---

## Installation & Setup

1. **Clone the repo**  
   ```bash
   git clone https://github.com/stick231/tg-bot-iskra.git
   cd tg-bot-iskra

2. **Install dependencies**
    ```bash
   composer install

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   # Edit .env:
   #   BOT_TOKEN=your_telegram_token
   #   DB_HOST=127.0.0.1
   #   DB_DATABASE=your_db
   #   DB_USERNAME=your_user
   #   DB_PASSWORD=your_pass

4. **Run database migrations**
   ```bash
   php artisan migrate
5. **Start the bot locally**
   ```bash
   php artisan serve
6. **Expose your local bot to the Internet via ngrok**
   ```bash
   ngrok http 127.0.0.1:8000
7. **Set your Telegram webhook**
   ```bash
   curl -X POST "https://api.telegram.org/bot<YOUR_TG_BOT_TOKEN>/setWebhook" \
     -d "url=https://<YOUR-NGROK-URL>/api/webhook"

## 📝 Environment Variables

- You must create a `.env` file based on `.env.example` and update it with your actual configuration.

---

## Commands

### Available (✅)

- `/start` — register or welcome the user  
- `/show_tasks` — list all your pending tasks  
- `/add_task` — create a new task (uses interactive state flow)  
- `/complete_task` — mark the last shown task as complete  

### In Development (🚧)

- `/statistics` — display summary stats (total tasks, completed, pending)  

## 💡 Contributing

Feel free to fork the repo and submit pull requests!

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

**Manage your tasks. Stay focused.**

# tg‑bot‑iskra

Самостоятельно развертываемый Telegram-бот для управления задачами: создание задач, напоминания и отслеживание прогресса — работает на вашем сервере без зависимости от сторонних сервисов.

---

## Требования

- **PHP** 8.1 или новее  
- **Composer**  
- **MySQL** или **MariaDB**  
- **Токен Telegram бота**  
- Скопируйте `.env.example` → `.env` и заполните настройки  

---

## Установка и настройка

1. **Клонируйте репозиторий**  
   ```bash
   git clone https://github.com/stick231/tg-bot-iskra.git
   cd tg-bot-iskra

2. **Установите зависимости**
   ```bash
   composer install

3. **Настройте окружение**
   ```bash
   cp .env.example .env
   php artisan key:generate
   # Отредактируйте .env:
   #   BOT_TOKEN=ваш_токен_бота
   #   DB_HOST=127.0.0.1
   #   DB_DATABASE=ваша_бд
   #   DB_USERNAME=ваш_пользователь
   #   DB_PASSWORD=ваш_пароль

4. **Запустите миграции БД**
   ```bash
   php artisan migrate

5. **Запустите бота локально**
   ```bash
   php artisan serve

6. **Настройте доступ через ngrok**
   ```bash
   ngrok http 127.0.0.1:8000

7. **Установите вебхук для Telegram**
   ```bash
   curl -X POST "https://api.telegram.org/bot<ВАШ_ТОКЕН>/setWebhook" \
     -d "url=https://<NGROK-URL>/api/webhook"

## 📝 Конфигурация

Создайте и настройте файл `.env` на основе примера `.env.example`.

---

## Доступные команды

### Реализовано (✅)

- `/start` — регистрация и приветствие  
- `/show_tasks` — список активных задач  
- `/add_task` — добавление новой задачи (интерактивный режим)
- `/complete_task` — отметка задачи как выполненной   

### В разработке (🚧)

- `/statistics` — статистика выполнения задач  

## 💡 Участие в разработке

Приветствуются форки и пул-реквесты!

---

## 📄 Лицензия

Распространяется под [лицензией MIT](LICENSE).

---

**Эффективное управление задачами. Максимальная продуктивность.**
