<?php

/**
 * @file classes/manager/form/UserManagementForm.inc.php
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserManagementForm
 * @ingroup manager_form
 *
 * @brief Form for journal managers to edit user profiles.
 *
 * CHANGELOG
 *	20110817	BLH	Change form checks to reflect eScholarship use.
 */

// $Id$


import('lib.pkp.classes.form.Form');

class UserManagementForm extends Form {

	/** The ID of the user being edited */
	var $userId;

	/**
	 * Constructor.
	 */
	function UserManagementForm($userId = null) {
		parent::Form('manager/people/userProfileForm.tpl');

		if (!Validation::isJournalManager()) $userId = null;
		$this->userId = isset($userId) ? (int) $userId : null;
		$site =& Request::getSite();

		// Validation checks for this form
		if ($userId == null) {
			$this->addCheck(new FormValidator($this, 'username', 'required', 'user.profile.form.usernameRequired'));
			$this->addCheck(new FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByUsername'), array($this->userId, true), true));
			//$this->addCheck(new FormValidatorAlphaNum($this, 'username', 'required', 'user.register.form.usernameAlphaNumeric'));
			$this->addCheck(new FormValidatorEmail($this, 'username', 'required', 'user.profile.form.emailRequired')); //using email as username
		}
		//$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array($this->userId, true), true));
		$this->addCheck(new FormValidator($this, 'firstName', 'required', 'user.profile.form.firstNameRequired'));
		$this->addCheck(new FormValidator($this, 'lastName', 'required', 'user.profile.form.lastNameRequired'));
		if ($userId == null) {
			if (!Config::getVar('security', 'implicit_auth')) {
				$this->addCheck(new FormValidator($this, 'password', 'required', 'user.profile.form.passwordRequired'));
				$this->addCheck(new FormValidatorLength($this, 'password', 'required', 'user.register.form.passwordLengthTooShort', '>=', $site->getMinPasswordLength()));
				$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', create_function('$password,$form', 'return $password == $form->getData(\'password2\');'), array(&$this)));
			}
		} else {
			$this->addCheck(new FormValidatorLength($this, 'password', 'optional', 'user.register.form.passwordLengthTooShort', '>=', $site->getMinPasswordLength()));
			$this->addCheck(new FormValidatorCustom($this, 'password', 'optional', 'user.register.form.passwordsDoNotMatch', create_function('$password,$form', 'return $password == $form->getData(\'password2\');'), array(&$this)));
		}		
		$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Display the form.
	 */
	function display($request = NULL, $template = NULL) {
		$userDao =& DAORegistry::getDAO('UserDAO');
		$templateMgr =& TemplateManager::getManager();
		$site =& Request::getSite();

		$templateMgr->assign('genderOptions', $userDao->getGenderOptions());
		$templateMgr->assign('minPasswordLength', $site->getMinPasswordLength());
		$templateMgr->assign('source', Request::getUserVar('source'));
		$templateMgr->assign('userId', $this->userId);
		if (isset($this->userId)) {
			$user =& $userDao->getUser($this->userId);
			$templateMgr->assign('username', $user->getUsername());
			$helpTopicId = 'journal.users.index';
		} else {
			$helpTopicId = 'journal.users.createNewUser';
		}

		$journal =& Request::getJournal();
		$journalId = $journal == null ? 0 : $journal->getId();
		import('pages.manager.PeopleHandler');
		$rolePrefs = PeopleHandler::retrieveRoleAssignmentPreferences($journalId);
		$activeRoles = array(
				'' => 'manager.people.doNotEnroll',
				'manager' => 'user.role.manager',
				'editor' => 'user.role.editor',
				'sectionEditor' => 'user.role.sectionEditor',
				);
		foreach($rolePrefs as $roleKey=>$use) {
			if($use){
				switch($roleKey){

				case 'useLayoutEditors': $activeRoles['layoutEditor'] = 'user.role.layoutEditor';break;
				case 'useCopyeditors': $activeRoles['copyeditor'] = 'user.role.copyeditor';break;
				case 'useProofreaders': $activeRoles['proofreader'] = 'user.role.proofreader';break;

				}
			}
		}
		$activeRoles = array_merge($activeRoles,array(
					'reviewer'=>'user.role.reviewer',
					'author'=>'user.role.author',
					'reader'=>'user.role.reader',
					'subscriptionManager' => 'user.role.subscriptionManager'
					)
				);

		if (Validation::isJournalManager()) $templateMgr->assign('roleOptions',$activeRoles);
		else $templateMgr->assign('roleOptions', // Subscription Manager
			array(
				'' => 'manager.people.doNotEnroll',
				'reader' => 'user.role.reader'
			)
		);

		// Send implicitAuth setting down to template
		$templateMgr->assign('implicitAuth', Config::getVar('security', 'implicit_auth'));

		$site =& Request::getSite();
		$templateMgr->assign('availableLocales', $site->getSupportedLocaleNames());

		$templateMgr->assign('helpTopicId', $helpTopicId);

		$countryDao =& DAORegistry::getDAO('CountryDAO');
		$countries =& $countryDao->getCountries();
		$templateMgr->assign_by_ref('countries', $countries);

		$authDao =& DAORegistry::getDAO('AuthSourceDAO');
		$authSources =& $authDao->getSources();
		$authSourceOptions = array();
		foreach ($authSources->toArray() as $auth) {
			$authSourceOptions[$auth->getAuthId()] = $auth->getTitle();
		}
		if (!empty($authSourceOptions)) {
			$templateMgr->assign('authSourceOptions', $authSourceOptions);
		}

		// Set list of institutions for eSchol
		$institutionList = array(
			'UC Berkeley' => 'UC Berkeley', 
			'UC Davis' => 'UC Davis', 
			'UC Irvine' => 'UC Irvine', 
			'UC Los Angeles' => 'UC Los Angeles', 
			'UC Merced' => 'UC Merced', 
			'UC Riverside' => 'UC Riverside', 
			'UC San Diego' => 'UC San Diego', 
			'UC San Francisco' => 'UC San Francisco', 
			'UC Santa Barbara' => 'UC Santa Barbara', 
			'UC Santa Cruz' => 'UC Santa Cruz', 
			'UC Office of the President' => 'UC Office of the President', 
			'Lawrence Berkeley National Lab' => 'Lawrence Berkeley National Lab'
		);
		$templateMgr->assign('institutionList', $institutionList);
		parent::display();
	}

	/**
	 * Initialize form data from current user profile.
	 */
	function initData() {
		$interestDao =& DAORegistry::getDAO('InterestDAO');
		if (isset($this->userId)) {
			$userDao =& DAORegistry::getDAO('UserDAO');
			$user =& $userDao->getUser($this->userId);

			// Get all available interests to populate the autocomplete with
			if ($interestDao->getAllUniqueInterests()) {
				$existingInterests = $interestDao->getAllUniqueInterests();
			} else $existingInterests = null;
			// Get the user's current set of interests
			if ($interestDao->getInterests($user->getId())) {
				$currentInterests = $interestDao->getInterests($user->getId());
			} else $currentInterests = null;

			if ($user != null) {
				$this->_data = array(
					'authId' => $user->getAuthId(),
					'username' => $user->getUsername(),
					'salutation' => $user->getSalutation(),
					'firstName' => $user->getFirstName(),
					'middleName' => $user->getMiddleName(),
					'lastName' => $user->getLastName(),
					'signature' => $user->getSignature(null), // Localized
					'initials' => $user->getInitials(),
					'gender' => $user->getGender(),
					'affiliation' => $user->getAffiliation(null), // Localized
					'email' => $user->getEmail(),
					'userUrl' => $user->getUrl(),
					'phone' => $user->getPhone(),
					'fax' => $user->getFax(),
					'mailingAddress' => $user->getMailingAddress(),
					'country' => $user->getCountry(),
					'biography' => $user->getBiography(null), // Localized
					'professionalTitle' => $user->getProfessionalTitle(null), // Localized
					'existingInterests' => $existingInterests,
					'interestsKeywords' => $currentInterests,
					'gossip' => $user->getGossip(null), // Localized
					'userLocales' => $user->getLocales()
				);

			} else {
				$this->userId = null;
			}
		}
		if (!isset($this->userId)) {
			$roleDao =& DAORegistry::getDAO('RoleDAO');
			$roleId = Request::getUserVar('roleId');
			$roleSymbolic = $roleDao->getRolePath($roleId);

			$this->_data = array(
				'enrollAs' => array($roleSymbolic)
			);
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array(
			'authId',
			'enrollAs',
			'password',
			'password2',
			'salutation',
			'firstName',
			'middleName',
			'lastName',
			'gender',
			'initials',
			'signature',
			'affiliation',
			'affiliationOther',
			'email',
			'userUrl',
			'phone',
			'fax',
			'mailingAddress',
			'country',
			'biography',
			'professionalTitle',
			'interestsKeywords',
			'interests',
			'gossip',
			'userLocales',
			'generatePassword',
			'sendNotify',
			'mustChangePassword'
		));
		if ($this->userId == null) {
			$this->readUserVars(array('username'));
		}

		if ($this->getData('userLocales') == null || !is_array($this->getData('userLocales'))) {
			$this->setData('userLocales', array());
		}

		if ($this->getData('username') != null) {
			// Usernames must be lowercase
			$this->setData('username', strtolower($this->getData('username')));
		}

		$interests = $this->getData('interestsKeywords');
		if ($interests != null && is_array($interests)) {
			// The interests are coming in encoded -- Decode them for DB storage
			$this->setData('interestsKeywords', array_map('urldecode', $interests));
		}
	}

	function getLocaleFieldNames() {
		$userDao =& DAORegistry::getDAO('UserDAO');
		return $userDao->getLocaleFieldNames();
	}

	/**
	 * Register a new user.
	 */
	function execute() {
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();

		if (isset($this->userId)) {
			$user =& $userDao->getUser($this->userId);
		}

		if (!isset($user)) {
			$user = new User();
		}

		// set value of affiliation
		// BLH 20131018 I'm not sure how to best deal with the localization here. This is a bit of a kluge since we're only going to use this locally at CDL.
		$affiliation = $this->getData('affiliation');
		if ($affiliation['en_US'] == 'Other') {
			$affiliationOther = $this->getData('affiliationOther');
			$affiliation = array('en_US' => $affiliationOther);
		}

		$user->setSalutation($this->getData('salutation'));
		$user->setFirstName($this->getData('firstName'));
		$user->setMiddleName($this->getData('middleName'));
		$user->setLastName($this->getData('lastName'));
		$user->setInitials($this->getData('initials'));
		$user->setGender($this->getData('gender'));
		$user->setAffiliation($affiliation, null); // Localized
		$user->setSignature($this->getData('signature'), null); // Localized
		$user->setEmail($this->getData('email'));
		$user->setUrl($this->getData('userUrl'));
		$user->setPhone($this->getData('phone'));
		$user->setFax($this->getData('fax'));
		$user->setMailingAddress($this->getData('mailingAddress'));
		$user->setCountry($this->getData('country'));
		$user->setBiography($this->getData('biography'), null); // Localized
		$user->setProfessionalTitle($this->getData('professionalTitle'), null); // Localized
		$user->setGossip($this->getData('gossip'), null); // Localized
		$user->setMustChangePassword($this->getData('mustChangePassword') ? 1 : 0);
		$user->setAuthId((int) $this->getData('authId'));

		$site =& Request::getSite();
		$availableLocales = $site->getSupportedLocales();

		$locales = array();
		foreach ($this->getData('userLocales') as $locale) {
			if (Locale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
				array_push($locales, $locale);
			}
		}
		$user->setLocales($locales);

		if ($user->getAuthId()) {
			$authDao =& DAORegistry::getDAO('AuthSourceDAO');
			$auth =& $authDao->getPlugin($user->getAuthId());
		}

		if ($user->getId() != null) {
			$userId = $user->getId();
			if ($this->getData('password') !== '') {
				if (isset($auth)) {
					$auth->doSetUserPassword($user->getUsername(), $this->getData('password'));
					$user->setPassword(Validation::encryptCredentials($userId, Validation::generatePassword())); // Used for PW reset hash only
				} else {
					$user->setPassword(Validation::encryptCredentials($user->getUsername(), $this->getData('password')));
				}
			}

			if (isset($auth)) {
				// FIXME Should try to create user here too?
				$auth->doSetUserInfo($user);
			}

			$userDao->updateObject($user);

		} else {
			$user->setUsername($this->getData('username'));
			if ($this->getData('generatePassword')) {
				$password = Validation::generatePassword();
				$sendNotify = true;
			} else {
				$password = $this->getData('password');
				$sendNotify = $this->getData('sendNotify');
			}

			if (isset($auth)) {
				$user->setPassword($password);
				// FIXME Check result and handle failures
				$auth->doCreateUser($user);
				$user->setAuthId($auth->authId);				$user->setPassword(Validation::encryptCredentials($user->getId(), Validation::generatePassword())); // Used for PW reset hash only
			} else {
				$user->setPassword(Validation::encryptCredentials($this->getData('username'), $password));
			}

			$user->setDateRegistered(Core::getCurrentDate());
			$userId = $userDao->insertUser($user);

			$isManager = Validation::isJournalManager();

			if (!empty($this->_data['enrollAs'])) {
				foreach ($this->getData('enrollAs') as $roleName) {
					// Enroll new user into an initial role
					$roleDao =& DAORegistry::getDAO('RoleDAO');
					$roleId = $roleDao->getRoleIdFromPath($roleName);
					if (!$isManager && $roleId != ROLE_ID_READER) continue;
					if ($roleId != null) {
						$role = new Role();
						$role->setJournalId($journal->getId());
						$role->setUserId($userId);
						$role->setRoleId($roleId);
						$roleDao->insertRole($role);
					}
				}
			}

			if ($sendNotify) {
				// Send welcome email to user
				import('classes.mail.MailTemplate');
				$mail = new MailTemplate('USER_REGISTER');
				$mail->setFrom($journal->getSetting('contactEmail'), $journal->getSetting('contactName'));
				$mail->assignParams(array('username' => $this->getData('username'), 'password' => $password, 'userFullName' => $user->getFullName()));
				$mail->addRecipient($user->getEmail(), $user->getFullName());
				$mail->send();
			}
		}

		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		$interestManager->insertInterests($userId, $this->getData('interestsKeywords'), $this->getData('interests'));
	}
}

?>
