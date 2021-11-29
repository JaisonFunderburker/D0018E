<?php

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Check Password: returns true if password match hashed version
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function checkPassword($raw_pwd, $raw_salt, $hashed_pwd) {
    return 0 == strcmp(hash("sha256", $raw_pwd . $raw_salt), $hashed_pwd);
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Check Empty: returns true if any of the elements in array is empty
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function checkEmpty($inputArr) {

    foreach ($inputArr as $input) {
      if (empty($input)) { return true; }
    }

    return false;
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Check Email: returns true if email is valid
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function checkEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return true;
    }
    return false;
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Check For Existing Email: returns true if email already is in the table
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function checkForExistingEmail($conn, $email) {
    $emailArr = fetch_emails($conn);
    if ($emailArr != null && in_array($email, $emailArr)) {
      return true;
    }
    return false;
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Fetch Product: Returns all product and additional product information
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function fetch_products($conn) {
    $sql = "SELECT * FROM product LEFT JOIN product_add ON product.id = product_add.pid;"; // change later to filter products
    $result = $conn->query($sql);

    return $result;
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Add to Cart: Adds pid and cid as new entry into cart_item (NOT PREP. STMT.)
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function add_to_cart($conn, $pid, $uid) {
    $conn->query("INSERT INTO cart_item(pid, uid) VALUES (". $pid . ", " . $uid . ")");
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Fetch cart: returns all products a user has in their cart
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function fetch_cart($conn, $uid) {
    $sql = "SELECT p.* FROM cart_item ci, product p WHERE ci.uid = ? AND p.id = ci.pid;";
    $stmt = mysqli_stmt_init($conn);
    mysqli_stmt_prepare($stmt, $sql);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
      header("location: /bookworm/pages/checkout?error=STMT_FAILED");
      exit();
    }

    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    return $result;
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Fetch user: returns all users given uid and/or email
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function fetch_user($conn, $uid, $email) {
    $sql = "SELECT * FROM user WHERE id = ? OR email = ?;";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
      header("location: /bookworm/pages/signup?error=STMT_FAILED");
      exit();
    }

    mysqli_stmt_bind_param($stmt, "ss", $uid, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if ($row = mysqli_fetch_assoc($result)) { return $row;}
    else { return false; }
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Fetch emails: returns all email addresses in the user table
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function fetch_emails($conn) {
    $sql = "SELECT email FROM user";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
      header("location: /bookworm/pages/signup?error=STMT_FAILED");
      exit();
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if ($col = mysqli_fetch_assoc($result)) { return $col;}
    else { return false; }
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Create User: Creates a user from param.
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function createUser($conn, $fname, $lname, $email, $pwd) {
    $sql = "INSERT INTO user (fname, lname, email, pwd_sha256, pwd_salt) VALUES (?, ?, ?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
      header("location: /bookworm/pages/signup?error=STMT_FAILED");
      exit();
    }
    $salt = random_bytes(32);
    mysqli_stmt_bind_param($stmt, "sssss", $fname, $lname, $email, hash("sha256", $pwd . $salt), $salt);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("location: /bookworm/pages/signup?error=none");
    exit();
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Edit User: Edit a user from profile page.
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function editUser($conn, $newFname, $newLname, $newEmail, $newPwd, $oldPwd, $uid) {
    $userArr = fetch_user($conn, $uid, null);

    //Make sure the input old password matches the stored password
    if (!checkPassword($oldPwd, $userArr["pwd_salt"], $userArr["pwd_sha256"])) {
      header("location: ../pages/profile?error=WRONG_PASSWORD");
      exit();
    }

    //Make sure the input new password doesn't match the stored password
    if ($oldPwd == $newPwd) {
      header("location: ../pages/profile?error=NEW_PASSWORD_SAME_AS_OLD");
      exit();
    }

    $salt = null;
    if ($newFname == null){
      $newFname = $userArr["fname"];
    }
    if ($newLname == null){
      $newLname = $userArr["lname"];
    }
    if ($newEmail == null){
      $newEmail = $userArr["email"];
    }
    if ($newPwd == null){
      $newPwd = $oldPwd;
      $salt = $userArr["pwd_salt"];
    }
    else{
      $salt = random_bytes(32);
    }

    $sql = "UPDATE user SET fname=?, lname=?, email=?, pwd_sha256=?, pwd_salt=? WHERE id=?";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)){
      header("location: ../pages/profile?error=STMT_FAILED");
      exit();
    }

    mysqli_stmt_bind_param($stmt, "ssssss", $newFname, $newLname, $newEmail, hash("sha256", $newPwd . $salt), $salt, $userArr["id"]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("location: ../pages/profile?error=none");
    exit();
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Login User: Starts a session given correct email and pwd
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function loginUser($conn, $email, $pwd) {
    $userArr = fetch_user($conn, null ,$email);

    // Wrong username
    if ($userArr == false) {
      header("location: /bookworm/pages/login?error=WRONG_LOGIN");
      exit();
    }
    // Wrong password
    if (!checkPassword($pwd, $userArr['pwd_salt'], $userArr['pwd_sha256'])) {
      header("location: /bookworm/pages/login?error=WRONG_LOGIN");
      exit();
    }
    // Correct username and password
    else {
      session_start();
      $_SESSION["uid"] = $userArr["id"];
      header("location: /bookworm/index.php");
      exit();
    }
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Place Order: Place an order on one or multiple items
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function placeOrder($conn, $address, $uid) {
    /* Tell mysqli to throw an exception if an error occurs */
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();

    try {
      // Add order parent
      $sql = "INSERT INTO order_parent (address, status, uid) VALUES (?, ?, ?);";
      $stmt = mysqli_stmt_init($conn);
      mysqli_stmt_prepare($stmt, $sql);

      $status = "PENDING";
      mysqli_stmt_bind_param($stmt, "ssi", $address, $status, $uid);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      // Add order items
      $result = $conn->query("SELECT LAST_INSERT_ID();");
      if ($result2 = $result->fetch_assoc()) {
        $oid = $result2["LAST_INSERT_ID()"];

        $cartArr = fetch_cart($conn, $uid);

        while ($item = $cartArr->fetch_assoc()) {
          echo "While loop";
          $id = $item["id"];
          $price = $item["price"];
          createOrderItem($conn, $oid, $id, $price);
        }

        // Commit changes if this point is reached
        $conn->commit();
      }
    }
    catch (mysqli_sql_exception $exception) {
      $conn->rollback();
      throw $exception;
    }

    header("location: /bookworm/pages/checkout_done?status=ORDER_PLACED");
    exit();
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Create Order Item: Create an id for an item in an order
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  function createOrderItem($conn, $oid, $id, $price) {
    $sql = "INSERT INTO order_item (pid, oid, price) VALUES (?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);
    mysqli_stmt_prepare($stmt, $sql);

    mysqli_stmt_bind_param($stmt, "iii", $id, $oid, $price);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }

 ?>
