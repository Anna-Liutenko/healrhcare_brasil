@echo off
REM Create a simple test image (100x100 red PNG)
REM Using a base64-encoded minimal PNG

setlocal enabledelayedexpansion

REM Minimal 100x100 red PNG (base64 encoded)
set "png_b64=iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAYAAABw4pVUAAAA3UlEQVR4Xu3QMQEAAADCoPVPbQhfoAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAOA1v9QAAT+h/A=="

REM Decode and save
powershell -Command "[System.Convert]::FromBase64String('%png_b64%') | Set-Content 'test-image.png' -Encoding Byte"

echo Test image created: test-image.png
