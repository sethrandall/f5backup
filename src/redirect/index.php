<?php
$location = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
header("Location: $location");
?>