.PHONY: *

docker-build:
	sudo docker build -t petrknap.cz .

docker-run:
	sudo docker run -v $$(pwd)/../..:/var/www/html --rm petrknap.cz bash -c "cd /var/www/html/projects/petrknap.cz && ${ARGS}"
	make clean

web-server: clean
	sudo docker run -v $$(pwd)/../..:/var/www/html --rm petrknap.cz

clean:
	(cd ../../ && make clean)
	sudo chmod u+r+w-x+X,g+r-w-x+X,o+r-w-x+X ./ -R
	sudo chmod g+w,o+w ./.backup -R
	sudo chmod g+w,o+w ./.keepass -R
	sudo chmod g+w,o+w ./var -R
	rm -rf ./var/cache || true

composer:
	make docker-run ARGS="composer ${ARGS}"

composer-install:
	make composer ARGS="install"

deployment: composer-install
	make docker-run ARGS="./vendor/bin/deploy"

tests: composer-install
	make docker-run ARGS="./vendor/bin/phpunit ${ARGS}"
