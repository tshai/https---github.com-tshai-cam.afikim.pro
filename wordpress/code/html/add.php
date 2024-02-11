<?php

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Form Page</title>
</head>

<body>
    <h1>Form Inputs</h1>
    <div>

        <input type="text" name="q_type" id="q_type" value="add" readonly hidden>


        <input type="text" name="customer_id" id="customer_id" value="25" readonly hidden>
        <input type="email" name="customer_email" id="customer_email" value="raiifert@gmail.com" readonly hidden>


        <input type="text" name="user_id" id="user_id" value="1" readonly hidden>

        <div class="form-group">

            <input type="radio" id="is_domain_yes" name="is_domain" value="1">
            <label for="is_domain_yes">יש לי כבר אתר</label><br>

            <input type="radio" id="is_domain_no" name="is_domain" value="0">
            <label for="is_domain_no">אני אוסיף שם אחר כך</label>
        </div>

        <div class="form-group" style="display:none">

            <label for="domain_name">Domain Name:</label>
            <input type="text" name="domain_name" id="domain_name" value="" required>

        </div>

        <input type="submit" value="Submit" class="btn btn-default">
    </div>
    <script>

            const is_domain = document.getElementsByName("is_domain");


            // Add an onchange event listener to the radio buttons
            is_domain.addEventListener("change", function() {
                if (isDomainYes.value()=="1") {
                // Code to execute when "יש לי כבר אתר" is selected
                alert("You selected יש לי כבר אתר (1)");
                }
                else
                {
                    alert("You selected אני אוסיף שם אחר כך (0)");
                }
            });

            

      



        document.getElementById("myForm").addEventListener("submit", function(event) {
            event.preventDefault();

            const formData = new FormData(this);
            const url = "/wp-content/code/index.php";

            fetch(url, {
                    method: "GET",
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        return response.text();
                    } else {
                        throw new Error("Network response was not ok.");
                    }
                })
                .then(data => {
                    console.log("Response from the server:", data);
                    // Handle the server's response here
                })
                .catch(error => {
                    console.error("Fetch error:", error);
                    // Handle errors here
                });
        });
    </script>
</body>

</html>


