<?php
	
	/*
		Versio API class gemaakt door Rick Wolthuis (rwolthuis.nl / versio@rwolthuis.nl) en mag door iedereen worden gebruikt, verspreid en worden aangepast.
		
		Versie:		30-07-2016 00:44
		Licentie:	MIT License
		Copyright:	Rwolthuis.nl © 2016
	*/
	
	
	/* Versio API class. */
	class Versio_API
	{
		/* Plek voor het klanten ID. */
		private $klant_id = 12345;
		
		/* Het wachtwoord in SHA1 formaat. */
		private $klant_passwd = '';
		
		/* Sandbox aan (true) of uit (false)? */
		private $sandbox = true;
		
		/* Geef aan of het success veld mee gegeven moet worden als een request gelukt is. */
		private $show_success = false;
		
		/* Geef aan of het SSL certificaat van de API server geverifieerd moet worden. */
		/* Zie: https://curl.haxx.se/docs/sslcerts.html */
		private $verify_certificate = false;
		
		/* Plek om laatste foutmelding in op te slaan. */
		private $last_error;
		
		
		
		/* Functie om een API request uit te voeren. */
		public function api_send ($data)
		{
			/* Is de data een array? */
			if (!is_array ($data))
			{
				/* Nee, $data is geen array, dus maak hier een array van waarvan er word uit gegaan dat $data het commando bevat. */
				$data = Array ('command' => $data);
			}
			
			/* Plaats het klanten ID in de data array. */
			$data['klantId'] = $this->klant_id;
			
			/* Daarnaa het wachtwoord. */
			$data['klantPw'] = $this->klant_passwd;
			
			/* En geef aan of het om sandbox mode gaat of niet. */
			$data['sandBox'] = $this->sandbox; 
			
			/* Maak een curl object. */
			$ch = curl_init ('https://www.secure.versio.nl/api/api_server.php');
			
			/* Op poort 443. */
			curl_setopt ($ch, CURLOPT_PORT, 443); 
			
			/* Geef aan dat het om een POST request gaat. */
			curl_setopt ($ch, CURLOPT_POST, true);
			
			/* En geef de data velden mee. */
			curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query ($data));
			
			/* Geef aan dat we resultaat terug willen. */
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
			
			/* Geef als HTTP header mee dat de verbinding gesloten wordt. */
			curl_setopt ($ch, CURLOPT_HTTPHEADER, Array ('Connection: close'));
			
			/* Geef aan dat de request maximaal 120 sec (2 min) mag duren. */
			curl_setopt ($ch, CURLOPT_TIMEOUT, 120);
			
				/* Moet het SSL certificaat van de API server geverifieerd worden? */
				if ($this->verify_certificate === false)
				{
					/* Geef aan dat het SSL certificaat niet wordt gecontrolleerd. */
					curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false);
				}
			
			/* Voer de request uit, en sla het resultaat op. */
			$response = curl_exec ($ch);
			
			/* Sluit het curl object. */
			curl_close ($ch);
			
				/* Is de response leeg? */
				if (empty ($response))
				{
					/* Maak de last_error leeg. */
					$this->last_error = (Object) Array ();
					
					/* Plaats nu de foutmeldingen in de array. */
					$this->last_error->command_response_code = '100-000-000';
					$this->last_error->command_response_message = 'could not connect with server try again later';
					
					/* Gooi nu een foutmelding dat er wat fout gegaan is. */
					throw new Exception ($this->last_error->command_response_message);
				}
			
			/* Parse het resultaat nu. */
			$result = $this->parse_response ($response);
			
			/* Geef het resultaat terug. */
			return $result;
		}
		
		
		
		/* Functie om data van de API server te parsen. */
		private function parse_response ($data)
		{
			/* Sloop de data uit elkaar op enters. */
			$lines = explode ("\n", $data);
			
			/* Tel nu het aantal regels. */
			$count_lines = count ($lines);
			
			/* Maak een return array. */
			$return = Array ();
			
				/* Is de request gelukt? */
				if (strtolower ($lines[0]) == 'success=1')
				{
					/* Haal het aantal 'total_count' op. */
					$total_count = substr ($lines[1], 12);
					
					/* Sloop nu de eerste 2 elementen uit het array. */
					array_shift ($lines);
					array_shift ($lines);
					
						/* Doorloop alle lines. */
						foreach ($lines AS $line)
						{
							/* Is de line leeg? */
							if (empty ($line))
							{
								/* Ja, dus continue deze lus. */
								continue;
							}
							
							/* Haal de line uit elkaar op een = teken. */
							$param = explode ('=', $line);
							
							/* Haal nu de index van de param uit elkaar op het _ teken. */
							$param_sub = explode ('_', $param[0]);
							
							/* Defineer de index. */
							$param_index = ((count ($param_sub) > 1) ? end ($param_sub) : $param_sub[1]);
							
								/* Bestaat er een array met de index van het opgegeven nummer? */
								if (!isset ($return[$param_index]))
								{
									/* Nee, dus maak deze aan. */
									$return[$param_index] = (Object) Array ();
								}
							
							/* Sloop nu het laatste element uit de param_sub array. Deze bevat namelijk alleen een getal. */
							array_pop ($param_sub);
							
							/* Maak een key variabele aan. */
							$object_key = ((count ($param_sub) == 1) ? $param_sub[0] : implode ('_', $param_sub));
							
							/* Plaats nu het item in de array. */
							$return[$param_index]->$object_key = $param[1];
						}
					
					/* Reset de keys van de return array. */
					$return = array_values ($return);
					
						/* Dient het success veld mee gegeven te worden als een request gelukt is? */
						if ($this->show_success)
						{
							/* Plaats in de return array dat de request gelukt is. */
							$return['success'] = true;
						}
					
					/* Geef het return array terug. */
					return $return;
				}
				/* Request mislukt. */
				else
				{
					/* Maak van de last error een lege object. */
					$this->last_error = (Object) Array ();
					
					/* Sloop nu eerst het eerste element uit de lines array. */
					array_shift ($lines);
					
						/* Doorloop nu alle lines. */
						foreach ($lines AS $line)
						{
							/* Haal de string uit elkaar op het = teken. */
							$param = explode ('=', $line);
							
							/* Maak een object key. */
							$object_key = $param[0];
							
							/* Sla de line op in de last_error object. */
							$this->last_error->$object_key = $param[1];
						}
					
					/* Gooi nu de foutmelding dat het mislukt is. */
					throw new Exception ($this->last_error->command_response_message);
				}
		}
		
		
		
		/* Functie om laatste foutmelding op te halen. */
		public function get_error ()
		{
			/* Geef laatste foutmelding terug. */
			return $this->last_error;
		}
	}
	
?>