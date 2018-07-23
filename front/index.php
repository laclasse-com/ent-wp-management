<!DOCTYPE html>
<html lang="fr">
    <head>
  <meta charset="utf-8">
  <meta http-equiv="content-language" content="fr">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Portail Laclasse.com">
  <meta name="author" content="">
  <link rel="shortcut icon" href="img/favicon.png">

  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">

  <title>Laclasse.com - Blogs</title>

  <link href="<?= APP_PATH ?>/app/vendor/bootstrap/dist/css/bootstrap.min.css?v=<?=APP_VERSION?>" rel="stylesheet" />
  <link href="<?= APP_PATH ?>/app/vendor/laclasse-common-client/css/damier.css?v=<?=APP_VERSION?>" rel="stylesheet" />
  <link href="<?= APP_PATH ?>/app/vendor/laclasse-common-client/css/floating-buttons.css?v=<?=APP_VERSION?>" rel="stylesheet" />
  <link href="<?= APP_PATH ?>/app/vendor/laclasse-common-client/css/main.css?v=<?=APP_VERSION?>" rel="stylesheet" />
  <link href="<?= APP_PATH ?>/app/styles/aside.css?v=<?=APP_VERSION?>" rel="stylesheet" />
  <link href="<?= APP_PATH ?>/app/styles/main.css?v=<?=APP_VERSION?>" rel="stylesheet" />
  <link href="<?= APP_PATH ?>/app/styles/add-blog.css?v=<?=APP_VERSION?>" rel="stylesheet" />
  <link href="<?= APP_PATH ?>/app/styles/growl-notifications.css?v=<?=APP_VERSION?>" rel="stylesheet" />
 

  <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
  <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
  <![endif]-->
    </head>

    <body>

    <div class= "purcent-page" data-ng-app="blogsApp" data-ng-strict-di>
      <div class="row purcent-page" style="width: 100%; margin: 0">
        <div data-ui-view class="animate scale-fade purcent-page"></div>
      </div>
    </div>

  <!-- Bootstrap core JavaScript
      ================================================== -->
  <!-- Placed at the end of the document so the pages load faster -->
    <script src="<?= APP_PATH ?>/app/vendor/jquery/dist/jquery.min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/jquery-ui/jquery-ui.min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/underscore/underscore-min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular/angular.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-i18n/angular-locale_fr-fr.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-resource/angular-resource.min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-ui-router/release/angular-ui-router.min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-bootstrap/ui-bootstrap-tpls.min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-bootstrap-checkbox/angular-bootstrap-checkbox.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-animate/angular-animate.min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-ui-sortable/sortable.min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-cookies/angular-cookies.min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-growl-notifications/dist/angular-growl-notifications.min.js?v=<?=APP_VERSION?>"></script>
      <script src="<?= APP_PATH ?>/app/vendor/angular-sanitize/angular-sanitize.js?v=<?=APP_VERSION?>"></script>

      <script src="<?= APP_PATH ?>/app/scripts/app.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/route.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/defaults.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/controllers/aside-home.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/controllers/modal-add-blog.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/controllers/main-home.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/services/services.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/services/blogs.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/services/users.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/services/wordpress.js"></script>
      <script src="<?= APP_PATH ?>/app/scripts/directives/directives.js"></script>

        <script>
        angular.module('blogsApp')
          .constant('APP_PATH', '<?=APP_PATH?>')
          .constant('BLOGS_DOMAIN', '<?=DOMAIN_CURRENT_SITE?>')
          .constant('BLOGS_API_URL', '<?=BLOGS_API_URL?>')
          ;  
        </script>
    </body>
</html>
