.PHONY=deploy

ssh=ssh user@ip
dest=/dest
dir=/finalDest

deploy:
	git archive master -o master.zip
	rsync -a master.zip -e $(ssh):$(dest)
	$(ssh) "cd $(dest) && unzip -o ./master.zip -d $(dir)"
	$(ssh) "cd $(dir) && composer install --no-dev -optmize-autoloader"
	$(ssh) "cd $(dir) && ./cli migrate"
	$(ssh) "cd $(dir) && npm install && npm run production && rm master.zip"
	rm master.zip
