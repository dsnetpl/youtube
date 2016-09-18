youtube downloader GUI
=======

A simple Symfony application to queue and download youtube videos using youtube-dl.

An authenthication using User Panel Oauth is required.

Downloads are processed in background using `youtube:download` command which should
run in loop. It is adviseable to periodically run the `youtube:remove` command
to remove least recently downloaded files.
