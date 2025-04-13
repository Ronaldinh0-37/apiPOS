<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html; charset=utf-8");
$setWindowsRaw = file_get_contents("windowsState.txt");
$dataForJs = json_decode($setWindowsRaw, true);
?>
<html>
  <head>
    <meta charset="utf-8">
    <title>Windows State</title>
  </head>
  <body>
    <script>
      (function(){
        var data = <?php echo json_encode($dataForJs); ?>;
        window.parent.postMessage(data, "*");
      })();
    </script>
    <p>Procesando...</p>
  </body>
</html>