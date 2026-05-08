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