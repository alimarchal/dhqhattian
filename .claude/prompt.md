Create SoftDelete options for invoices, patient_tests in model and new migrations
add two routes 1 is /process and other is restore
when we add the /process then from randomly check the invoices of the user id 35 of the same date for example if i press the /process url from route then check today date according to system and check the invoices total_amount here is catch 

if randomly selected invoice of user_id 35 (only 35) of that same day from invoices table using user_id 35  
first sum the total_amount if (total_amount) > 15000 and less then 20000 then then deleted those invoice whose all sum is 2000 (soft delete) and it's realted patient_tests (you can check via invoice_id) make sure both scenerio should be compalted using a transaction if randomly selected invoice of user_id 35 (only 35)
 total_amount of that day is greater then 22000 then 3000 then  deleted those invoice whose all sum is 2000 (soft delete) when done only echo the message 
 in two Total Deduction: XXXX
