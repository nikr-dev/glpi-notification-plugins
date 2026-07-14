# GLPI Telegram and Mattermost notification plugins
Plugin for sending GLPI notifications via Mattermost or Telegram bot. Support sending to user and channel (or both).

!WARNING! it's a "vibecode" thing, cooked with DeepSeek. I will fully understand you for shaming me :)

Writen and tested with GLPI v11.0.8.
Will not work below 11 version.

Plugin registers as notification method (along with email, browser), and you must add notification templates with it to your notifications, like you did with emails!
<img width="1159" height="453" alt="изображение" src="https://github.com/user-attachments/assets/1b804543-94c9-4c6a-9d3a-2e0a1e589f44" />

Telegram and Mattermost user and channel ID fields added to user page. Leave it blank for disable sending to this user, fill both or just one - all should work.
<img width="1765" height="138" alt="изображение" src="https://github.com/user-attachments/assets/4f0d0567-1e5f-40e9-b9a6-d4d7df7780b7" />

Channed ID added to user for GLPI groups logic workaround.

