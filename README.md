# peolplespheresTests
PeopleSpheres - Backend Software Developer Technical Assessment – v22-02

# Context :
* NGINX and PHP containers available in the project.
* Compose also available in the project.

# Duty:
* Considering the containers and the descriptions, PHP is the best langage to me.

# Delivery location:
* This repository for the code and another one on Dockerhub for the containers.

# Task 01: Docker
* php-fpm service image   => OK
* nginx service           => OK
* self-signed certificate => OK

# Task 02: Problem solving
* Code available in this repository.
* It is missing some check on the query expression validity (parenthesis matching, input action names compared with the list of available functions)
* Some tricky cases with special characters (~, ?, :) inside the concatenated strings are not fully managed.
* Code could be improved from a performance point of view and could also be simplified to make it easier to maintain.
* eval() function not used. Not using eval as it is dangerous and allow the remote user to execute malicious code on server side. Moreover, I think that the eval function is not fully portable and does not behave exactly the same depending on the platform.
* Service available through endpoint : https://localhost:9443/api/v1.0/index.php

# Task 03: Bonus
* Done
* Swagger documentation available through endoint : https://localhost:9443/api/v1.0/swagger.html
* Documentation could be highly improved

