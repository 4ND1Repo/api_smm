# api_smm
This is an API for textile project in Bandung, West Java - Indonesia

Installation :
- Requirement Composer And Docker
- Open Terminal with access Composer and Docker
- Entry script : 
  - composer create-project laravel/lumen:5.7.* --prefer-dist api
  - cd ui && git init && git remote add http://github.com/4ND1Repo/api_smm
  - git pull && git reset --hard origin/dev && git checkout dev && git pull
  - docker-compose up -d