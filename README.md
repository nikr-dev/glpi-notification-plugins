# GLPI Mattermost notification plugin
Plugin for sending GLPI notifications via Mattermost bot. Support sending to user and channel (or both).

!WARNING! it's a "vibecode" thing, cooked with DeepSeek. I will fully understand you for shaming me :)

Writen and tested with GLPI v11.0.6.
Will not work below 11 version.

Plugin registers as notification method (along with email, browser), and you must add notification templates with it to your notifications, like you did with emails!
Mattermost user and channel ID fields added to user page. Leave it blank for disable sending to this user, fill both or just one - all should work.
Channed ID added to user for GLPI groups logic workaround.

