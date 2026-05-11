cd travelmate_web
face_env\Scripts\activate
python face_service.py 

deactivate
cd ai_service 
venv\Scripts\python -m uvicorn main:app --host 127.0.0.1 --port 8002 --reload

deactivate
cd travelmate_web
symfony server:stop
php bin/console cache:clear
composer install
php bin/console cache:clear
symfony server:start --no-tls --port=8001


git config --global  user.email "malek.fekih@esprit.tn"

#      DATABASE_URL="mysql://root:@127.0.0.1:3306/travelmate?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
#      DATABASE_URL="mysql://Travelmate:Travelmate@172.20.10.9:3306/travelmate?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
