<?php

	class Controllers {

		public static function Home() {
			$user = Service::$user;

			$passwordChanged = (isset($user->password_last_changed) ? timeSince($user->password_last_changed) . ' ago' : 'never');
			if(strpos($passwordChanged, 'year') || strpos($passwordChanged, 'month')) $passwordChanged .= '. You should change your password';

			Views::Render("home", array(
				'user'              => $user,
				'passwordChanged'   => $passwordChanged,
				'accountRegistered' => timeSince($user->registered) . ' ago'
			));
			Cleanup();
		}

		public static function Accounts() {
			$user = &Service::$user;

			$add_action_message  = null;
			$add_action_error    = null;
			$add_action_value    = '';

			$edit_action_message = null;
			$edit_action_error   = null;

			if(isset($_POST['activity'])) {

				if($_POST['activity'] == 'add') {
					$ret = Service::registerAddress($_POST['email']);
					if(isset($ret->success) && $ret->success) {
						$add_action_message = 'Address registered successfully.';
						$user->emails = $ret->emails;
					} else {
						$add_action_value = $_POST['email'];
						if(isset($ret->error)) {
							$add_action_error = $ret->error;
						} else {
							$add_action_error = 'There was a problem registering this address.';
						}
					}
				} elseif($_POST['activity'] == 'promote') {
					$ret = Service::promoteAddress($_POST['email']);
					if(isset($ret->success) && $ret->success) {
						$edit_action_message = "Address promoted successfully. {$_POST['email']} is now your primary account.";
						$user->emails = $ret->emails;
					} else {
						if(isset($ret->error)) {
							$edit_action_error = $ret->error;
						} else {
							$edit_action_error = 'There was a problem promoting this address.';
						}
					}
				} elseif($_POST['activity'] == 'remove') {
					$ret = Service::removeAddress($_POST['email']);
					if(isset($ret->success) && $ret->success) {
						$edit_action_message = "Address removed successfully.";
						$user->emails = $ret->emails;
					} else {
						if(isset($ret->error)) {
							$edit_action_error = $ret->error;
						} else {
							$edit_action_error = 'There was a problem removing this address.';
						}
					}
				}

			}

			Views::Render("accounts", array(
				'user' => $user,
				'add_action_message'  => $add_action_message,
				'add_action_error'    => $add_action_error,
				'add_action_value'    => $add_action_value,
				'edit_action_message' => $edit_action_message,
				'edit_action_error'   => $edit_action_error
			));
			Cleanup();
		}

		public static function Security() {
			global $secretQuestions;
			$user = &Service::$user;

			$challenge_question      = null;

			$password_update_message = null;
			$password_update_error   = null;

			$question_update_message = null;
			$question_update_error   = null;

			$yubikey_edit_message    = null;
			$yubikey_edit_error      = null;

			$passwordChanged = (isset($user->password_last_changed) ? timeSince($user->password_last_changed) . ' ago' : 'never');
			if(strpos($passwordChanged, 'year') || strpos($passwordChanged, 'month')) $passwordChanged .= '. You should change your password';

			if(isset($_POST['activity'])) {

				if($_POST['activity'] == 'password') {
					$password = (isset($_POST['password']) ? $_POST['password'] : null);
					$new_password = (isset($_POST['new_password']) ? $_POST['new_password'] : null);
					$confirm_password = (isset($_POST['confirm_password']) ? $_POST['confirm_password'] : null);

					if($password) {
						if($new_password && $confirm_password) {
							if(strlen($new_password) > 5 && strlen($new_password) < 128) {
								if($new_password === $confirm_password) {
									if(strtoupper($new_password) !== $new_password) {
										if($password_check = Service::checkPassword($password)) {
											if($password_check->success) {
												if($password_check = Service::changePassword($new_password)) {
													$password_update_message = 'Your password has been updated.';
												} else {
													$password_update_error = $password_check->error;
												}

											} else {
												$password_update_error = $password_check->error; //'The account password you provided was incorrect. Please try again.';
											}

										} else {
											$password_update_error = 'We encountered a technical problem. Please try again later.';
										}

									} else {
										$password_update_error = 'Your password appears to be fully capitalized. Do you have your caps lock on?';
									}

								} else {
									$password_update_error = 'Your password confirmation does not match.';
								}

							} else {
								$password_update_error = 'Your new password must be between 5 and 128 characters in length.';
							}

						} else {
							$password_update_error = 'You must provide and confirm your new password choice.';
						}

					} else {
						$password_update_error = 'You must provide your password.';
					}

				} elseif($_POST['activity'] == 'yubikey_remove') {
					if($removal = Service::deleteYubikey()) {
						if($removal->success) {
							$yubikey_edit_message = 'Yubikey removed successfully.';
						} else {
							$yubikey_edit_error = 'There was a problem removing your Yubikey.';
						}
					} else {
						$yubikey_edit_error = 'We encountered a technical problem. Please try again later.';
					}

				} elseif($_POST['activity'] == 'yubikey_pair') {
					$otp = (isset($_POST['otp']) ? htmlentities(trim(strip_tags($_POST['otp']))) : null);

					if($otp) {
						if($paired = Service::setYubikey($otp)) {
							if($paired->success) {
								$yubikey_edit_message = 'Yubikey paired successfully.';
							} else {
								$yubikey_edit_error = $paired->error;
							}
						} else {
							$yubikey_edit_error = 'We encountered a technical problem. Please try again later.';
						}
					} else {
						$yubikey_edit_error = 'You must provide a Yubikey OTP passcode to pair.';
					}

				} elseif($_POST['activity'] == 'secret_question') {
					$question = (isset($_POST['question']) ? htmlentities(trim(strip_tags($_POST['question']))) : null);
					$answer = (isset($_POST['answer']) ? htmlentities(trim(strip_tags($_POST['answer']))) : null);

					if($question) {
						$challenge_question = $question;

						if($answer) {
							if(strlen($answer) > 3) {
								if($question = Service::setChallengeQuestion($question)) {
									if($question->success) {
										if($answer = Service::setChallengeAnswer($answer)) {
											if($answer->success) {
												$question_update_message = 'Your question and answer have been updated successfully.';

											} else {
												$question_update_error = $question->error;

											}

										} else {
											$password_update_error = 'We encountered a technical problem. Please try again later.';

										}
									} else {
										$question_update_error = $question->error;
									}

								} else {
									$password_update_error = 'We encountered a technical problem. Please try again later.';

								}

							} else {
								$question_update_error = 'Your answer must be more than 3 characters long.';

							}

						} else {
							$question_update_error = 'You must provide an answer.';

						}

					} else {
						$question_update_error = 'You must select a question.';

					}

				}

			}

			if($challenge_question) {
				$challenge_question  = '<option value="" disabled="disabled">Select One &hellip;</option>' .
				                       '<option selected="selected">' . $challenge_question . '</option>';
			} else {
				$challenge_question = Service::getChallengeQuestion();

				if($challenge_question && $challenge_question->success && $challenge_question->question) {
					$challenge_question  = '<option value="" disabled="disabled">Select One &hellip;</option>' .
					                       '<option selected="selected">' . $challenge_question->question . '</option>';
				} else {
					$challenge_question = '<option value="" selected="selected" disabled="disabled">Select One &hellip;</option>';
				}
			}

			$questions = array();
			for($i = 0; $i <= 10; $i++) {
				$r = null;
				while($r == null || isset($questions[$r])) {
					$r = mt_rand(0, count($secretQuestions) - 1);
				}
				if(strpos($challenge_question, $secretQuestions[$r])) {
					$i = $i - 1; continue;
				}
				$questions[$r] = $secretQuestions[$r];
			}

			$yubi_paired = Service::getYubikeyPair();
			if(isset($yubi_paired->success) && $yubi_paired->success && isset($yubi_paired->paired)) {
				$yubi_paired = $yubi_paired->paired;
			} else {
				$yubi_paired = false;
			}

			Views::Render("security", array(
				'user'                    => $user,
				'questions'               => $questions,
				'passwordChanged'         => $passwordChanged,
				'password_update_message' => $password_update_message,
				'password_update_error'   => $password_update_error,
				'challenge_question'      => $challenge_question,
				'question_update_message' => $question_update_message,
				'question_update_error'   => $question_update_error,
				'yubi_paired'             => $yubi_paired,
				'yubikey_edit_message'    => $yubikey_edit_message,
				'yubikey_edit_error'      => $yubikey_edit_error

			));
			Cleanup();
		}

		public static function Login() {
			Views::Render("login");
			Cleanup();
		}

		public static function Logout() {
			Sessions::ResetCookie();
			Sessions::storagePut('login_message', 'You have been successfully logged out.', true);
			Views::Redirect('login');
		}

	}

