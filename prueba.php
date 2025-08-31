<?php
$file = '/var/www/greentrack/app/logs/modelo/test.log';
file_put_contents($file, date('Y-m-d H:i:s') . ' - Prueba de escritura\n', FILE_APPEND);
echo 'OK';
?>

App ID: fleetmatics-p-us-O62cWyYffe6K5Y4fMO8hEwbZrw80ZKQmv2JZWAKm


curl -k -X POST "https://fim.api.us.fleetmatics.com/push/v1/subscriptions" \
     -H "Authorization: Atmosphere atmosphere_app_id=fleetmatics-p-us-O62cWyYffe6K5Y4fMO8hEwbZrw80ZKQmv2JZWAKm, Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjJDREY5REVGNkI2RDMwNTM3QTdFRTZENzI3MDYxQzkxQzgzMTQ1MjkiLCJ0eXAiOiJKV1QiLCJ4NXQiOiJMTi1kNzJ0dE1GTjZmdWJYSndZY2tjZ3hSU2sifQ.eyJuYmYiOjE3NTU2MTU5MjIsImV4cCI6MTc1NTcwMjMyMiwiaXNzIjoiaHR0cHM6Ly9hdXRoLnVzLmZsZWV0bWF0aWNzLmNvbS90b2tlbiIsImF1ZCI6WyJodHRwczovL2F1dGgudXMuZmxlZXRtYXRpY3MuY29tL3Rva2VuL3Jlc291cmNlcyIsIm9wZW5pZCIsInByb2ZpbGUiLCJyZXZlYWwiXSwiY2xpZW50X2lkIjoicmV2ZWFsLWV4dGVybmFsdG9rZW4tYXBpIiwic3ViIjoiZjU0M2ZlMDgtNTEwYS00MGQ1LTBiOWYtMDhkZGQ1ODU2NGM3IiwiYXV0aF90aW1lIjoxNzU1NjE1OTIyLCJpZHAiOiJsb2NhbCIsInJldmVhbF9hY2NvdW50X2lkIjoiMTIzMzY5MSIsInJldmVhbF91c2VyX2lkIjoiNzE0OTI5MyIsInJldmVhbF91c2VyX3R5cGVfaWQiOiIzIiwidW5pcXVlX25hbWUiOiJSRVNUX1Bvc2l0cm9uVFhfNjIwMEAxMjMzNjkxLmNvbSIsInByZWZlcnJlZF91c2VybmFtZSI6IlJFU1RfUG9zaXRyb25UWF82MjAwQDEyMzM2OTEuY29tIiwibmFtZSI6IlJFU1RfUG9zaXRyb25UWF82MjAwQDEyMzM2OTEuY29tIiwiZW1haWwiOiJSRVNUX1Bvc2l0cm9uVFhfNjIwMEAxMjMzNjkxLmNvbSIsImVtYWlsX3ZlcmlmaWVkIjpmYWxzZSwianRpIjoiYzM2ODBjZGQ5MmIxYzNiM2VjMGJkM2ExNGFjYWJhMDkiLCJpYXQiOjE3NTU2MTU5MjIsInNjb3BlIjpbIm9wZW5pZCIsInByb2ZpbGUiLCJyZXZlYWwiXSwiYW1yIjpbInB3ZCJdfQ.Dnc8To2Qx7AVDOkuZJvte-9hr_ONDH8WmGHIPkLTqqRzhS_Vn8GaScMdiQRsE3NTJGH6sHZsGj-BA3GsAljRliy8F2Lmod9aY0HW_-IyBm9NFhoAQ6wqae72iqiEEHrr1UHDy60-4dgBPxKowwylVJaSpY8qd6JcmDZ3hdSfcMxzrf2F76QQckKWeru89mCbM3tB-zSEr8iie6juUCOjoIgNeE5EafM0ktZYQK-fpLFJfz0e5sPlqeZStn1XQ3FG9lL0eN_ngO4kdqXiaP1r-F9bWM8sGyGGf82G4weIMasgXvGXPV3_fnr01F5GqtVr9GdT9caM5UjLF1I0c0kOVg" \
     -H "Content-Type: application/json" \
     -d '{
           "eventTypes": ["com.verizonconnect.integrations.vehicle.position.updated"],
           "callbackUrl": "https://positron4tx.ddns.net:9990/webhooks/verizon/gps.php",
           "description": "GreenTrack Live - GPS Tracking"
         }'