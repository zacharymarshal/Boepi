<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title></title>
  <meta name="description" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo $cssTag('app/boepi/css/application.css') ?>
</head>
<body>
    <div class="container">
        <a href="<?php echo $authorizeUrl ?>" class="btn btn-default">Access Github <i class="fa fa-github"></i></a>
    </div>
    <?php echo $jsTag('app/boepi/js/application.js.coffee') ?>
</body>
</html>
