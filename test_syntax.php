<?php<?php

// Simple syntax check - try to include the file// Test file to check PHP syntax structure

include_once 'detail.php';echo "Testing PHP syntax...";

echo "File syntax is OK\n";

?>// Check if any PHP conditionals are properly closed
if (true) {
    echo "Test 1 passed";
}

if (false):
    echo "Test 2";
else:
    echo "Test 2 alternative";
endif;

echo "End of test";
?>
<!DOCTYPE html>
<html>
<head><title>Test</title></head>
<body>
<p>HTML content</p>
</body>
</html>