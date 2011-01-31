.PHONY: publish publishprod                       


ARCH:=$(shell uname)

publish: send fixperms


publishprod: sendprod fixpermsprod


send:
        rsync -avz . root@blogs.laclasse.lan:/var/www/wpmu/wp-content/plugins/ENT-WP-management --exclude .git --exclude .gitignore --exclude doc/

sendprod:
        rsync -avz . root@blogs.laclasse.com:/var/www/wordpress/wp-content/plugins/ENT-WP-management --exclude .git --exclude .gitignore --exclude doc/

fixpermsprod:
        ssh root@blogs.laclasse.com "chown -R www-data:www-data /var/www/wordpress/wp-content/plugins/ENT-WP-management/"

fixperms:
        ssh root@blogs.laclasse.lan "chown -R www-data:www-data /var/www/wpmu/wp-content/plugins/ENT-WP-management/"


