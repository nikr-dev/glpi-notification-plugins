# GLPI Telegram and Mattermost notification plugins
Plugin for sending GLPI notifications via Mattermost or Telegram bot. Support sending to user and channel (or both).

!WARNING! it's a "vibecode" thing, cooked with DeepSeek. I will fully understand you for shaming me :)

Writen and tested with GLPI v11.0.8.
Will not work below 11 version.
Channed ID added to user for GLPI groups logic workaround.

Plugins registers as notification method (along with email, browser), and you must add notification templates with it to your notifications, like you did with emails!
<img width="1091" height="490" alt="изображение" src="https://github.com/user-attachments/assets/99a0a239-f56d-47b9-a81c-b1c40ce0b1cc" />

Mattermost user and channel ID fields added to user page. Leave it blank for disable sending to this user, fill both or just one - all should work.
<img width="1156" height="117" alt="изображение" src="https://github.com/user-attachments/assets/fe9ff339-25a9-4323-8c37-5bd19c4d6720" />

Telegram user and group ID must be filled with literally ids - supergroups (with - on start) is supported. Topics are supported too.
<img width="1395" height="131" alt="изображение" src="https://github.com/user-attachments/assets/5afa0fb4-8250-4be9-8353-f81f31b50ed6" />




