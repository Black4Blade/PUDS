<?php  
require "paypal.class.php";
require "rcon_code.php";
require "config.php";

	
$p = new paypal_class;
$p->paypal_url = $payPalURL; // $payPalURL is defined in config.php


	 $file = 'log.txt';
	 $current = file_get_contents($file);
//Database stuff
if($UseDB == "true"){	 
		$db=mysqli_connect($HOST,$DBUSER,$DBPASS,$DBNAME);
			$current .="\nConnected to database\n";
			file_put_contents($file, $current);
			// Check connection
			if (mysqli_connect_errno($db)) {
				echo "Failed to connect to MySQL: " . mysqli_connect_error();
				$current .="Failed to connect to database\n";
				file_put_contents($file, $current);
			} 
		$DBTABLE = mysqli_real_escape_string($db, $DBTABLE);
		$result = mysqli_query($db,"SHOW TABLES LIKE '".$DBTABLE."'");
		if (!$result) {
			$current .='Error: '.mysqli_error($db).'\n';
			file_put_contents($file, $current);
			exit();
		}
		$tableExists = mysqli_num_rows($result) > 0;
		
	if($tableExists){
		//connect to the table
		$current .="Table exists, Connecting to table.\n";
		file_put_contents($file, $current);
		mysqli_select_db($db, $DBTABLE);
	} else {
		//Create table
		$current .="Table does not exist, creating table.\n";
		file_put_contents($file, $current);
		$sql = "CREATE TABLE ".$DBTABLE." 
		(
		PID INT NOT NULL AUTO_INCREMENT, 
		PRIMARY KEY(PID),
		email VARCHAR(250),
		steamid VARCHAR(250),
		name VARCHAR(250),
		rank VARCHAR(250)
		)";
		mysqli_query($db,$sql);	
		mysqli_select_db($db, $DBTABLE);
	}	
}
		
if ($p->validate_ipn()) {
		$current .="IPN Validated.\n";
		file_put_contents($file, $current);
		$fee = $p->ipn_data['mc_gross'];
		$email = $p->ipn_data['payer_email']; 
		$name = $p->ipn_data['option_selection1'];
		$steamid = $p->ipn_data['option_selection2'];
		
		if (is_array($prices)){
			foreach($prices as $key => $val){
					$i++;
					if($val == $fee){
						$rank = $ranks[$i - 1];
						$command = $commands[$i - 1] .' '. $steamid.' '.$rank;
					}
			}
		} else {
			$current .='$prices is an not array.\n';
			file_put_contents($file, $current);
		}
		$current .=$email.' '.$name.' '.$fee .' '.$steamid.' '.$rank."\n";
		file_put_contents($file, $current);
		
		//Add user donation to database.
		if($UseDB == "true"){
			$sql = 	'INSERT INTO '.$DBTABLE.' (email, steamid, name, rank) VALUES ("'.mysqli_real_escape_string($db, $email).'", "'.mysqli_real_escape_string($db, $steamid).'", "'.mysqli_real_escape_string($db, $name).'", "'.mysqli_real_escape_string($db, $rank).'")';
			mysqli_query($db,$sql);	
			$current .="Added to database.\n";
			file_put_contents($file, $current);			
		}
		
		//Rcon connection to apply rank.
		$srcds_rcon = new srcds_rcon();
		$OUTPUT = $srcds_rcon->rcon_command($IP, $PORT, $PASSWORD, $command);
		$current .='IP: '.$IP.' Port: '.$PORT.' Password: HIDDEN Command: '.$command."\n";
		file_put_contents($file, $current);			
		$current .=$OUTPUT;
		file_put_contents($file, $current);  
		
		//Check reply from server
		if( $OUTPUT == 'Unable to connect!' || $OUTPUT == '' ) { 
			// Email Buyer - Donation complete - Rank failed	
			$current .="Unable to connect to Rcon, please check your configuration.\n";
			file_put_contents($file, $current);			
			$to      = $email;
			$subject = 'PUDS - Donation Complete - Rank failed to set: '.$rank.'';  
			mail($to, $subject, $messageRankFail, $headers);
		} else {
			// Email Buyer		
			$to      = $email;
			$subject = 'PUDS - Donation Complete: '.$rank.'';  
			$headers = 'From:PUDS PayPal-ULX Donation System' . "\r\n";  
			mail($to, $subject, $messageSuccess, $headers);
		}
		
}
else 
{
	// Email Buyer
	$to      = $email;
	$subject = 'PUDS - Donation Failed:';  
	$headers = 'From:PUDS PayPal-ULX Donation System' . "\r\n";  
	mail($to, $subject, $messageIPNFail, $headers);
}
if($UseDB == "true"){
	mysqli_close($db);  
 }
?>  