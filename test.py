import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin
import os
import random
import time
from pathlib import Path

BASE_URL = "https://crew.topeverphil.com.ph/uploads/"
SAVE_FOLDER = "downloads_topeverphil_uploads"
PROXY_FILE = "proxy.txt"   # your cleaned proxy list, one per line
MAX_RETRIES = 3                     # number of different proxies to try per file
REQUEST_TIMEOUT = 15                 # seconds for connection & read

# Create folder if it doesn't exist
Path(SAVE_FOLDER).mkdir(exist_ok=True)

def load_proxies(file_path):
    """Load proxies from a text file (ip:port or proto://ip:port)."""
    proxies = []
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#'):
                    # requests expects a dict: {'http': 'http://proxy:port', 'https': 'https://proxy:port'}
                    # If the line already has a protocol, use it; otherwise prepend 'http://'
                    if '://' not in line:
                        line = 'http://' + line
                    # For HTTPS sites, we can use the same proxy (requests will upgrade if needed)
                    proxies.append({
                        'http': line,
                        'https': line
                    })
        print(f"Loaded {len(proxies)} proxies from {file_path}")
    except FileNotFoundError:
        print(f"Proxy file {file_path} not found. Running without proxies.")
    return proxies

def download_with_proxy(url, save_path, proxy_list, max_retries=3):
    """
    Attempt to download a file using a random proxy from the list.
    If a proxy fails, try another one up to max_retries times.
    """
    tried_proxies = set()
    for attempt in range(max_retries):
        # Choose a proxy not yet tried (if any)
        available = [p for p in proxy_list if tuple(p.items()) not in tried_proxies]
        if not available:
            print("   No more proxies to try. Giving up.")
            return False

        proxy = random.choice(available)
        tried_proxies.add(tuple(proxy.items()))

        try:
            print(f"   Attempt {attempt+1} using proxy {proxy['http']}")
            with requests.get(url, stream=True, timeout=REQUEST_TIMEOUT, proxies=proxy) as r:
                r.raise_for_status()
                with open(save_path, 'wb') as f:
                    for chunk in r.iter_content(chunk_size=8192):
                        f.write(chunk)
            print(f"   ✓ Saved -> {save_path}")
            return True
        except requests.exceptions.ProxyError as e:
            print(f"   ✗ Proxy error: {e}")
        except requests.exceptions.Timeout:
            print(f"   ✗ Timeout")
        except requests.exceptions.RequestException as e:
            print(f"   ✗ Request failed: {e}")
        # If we get here, proxy failed – continue to next attempt
    return False

def download_all_files():
    # Load proxies
    proxy_list = load_proxies(PROXY_FILE)
    if not proxy_list:
        print("WARNING: No proxies loaded. Will attempt direct download (your real IP).")

    # Fetch directory listing
    try:
        print(f"Fetching directory listing from: {BASE_URL}")
        # Use a random proxy for the directory listing as well (optional)
        if proxy_list:
            proxy = random.choice(proxy_list)
            response = requests.get(BASE_URL, timeout=REQUEST_TIMEOUT, proxies=proxy)
        else:
            response = requests.get(BASE_URL, timeout=REQUEST_TIMEOUT)
        response.raise_for_status()
    except Exception as e:
        print(f"Cannot reach the server → {e}")
        return

    soup = BeautifulSoup(response.text, "html.parser")

    downloaded = 0
    skipped = 0

    for link in soup.find_all("a"):
        href = link.get("href")
        if not href:
            continue

        # Skip parent directory and subdirectories
        if href == "../" or href.endswith("/"):
            continue

        file_url = urljoin(BASE_URL, href)
        file_name = href.rstrip("/")
        save_path = os.path.join(SAVE_FOLDER, file_name)

        if os.path.exists(save_path):
            print(f"Already exists, skipping → {file_name}")
            skipped += 1
            continue

        print(f"\nDownloading → {file_name}")

        if proxy_list:
            success = download_with_proxy(file_url, save_path, proxy_list, MAX_RETRIES)
        else:
            # Fallback to direct download (no proxy)
            success = download_with_proxy(file_url, save_path, [{}], 1)  # empty proxy dict means direct

        if success:
            downloaded += 1
        else:
            print(f"   FAILED after all retries → {file_name}")

    print("\n" + "─"*60)
    print(f"Finished!")
    print(f"Downloaded: {downloaded} files")
    print(f"Skipped (already exist): {skipped} files")
    print(f"Saved in: ./{SAVE_FOLDER}")

if __name__ == "__main__":
    download_all_files()
