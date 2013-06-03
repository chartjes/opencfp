<?php

/**
 * Controller for when a user submits user data
 */
$form = new \OpenCFP\SignupForm($_POST);
$data = array();
$pageTemplate = 'create_user.twig';

$valid = $form->validateAll();
if ($valid) {
    $sanitizedData = $form->sanitize();

    // Create account using Sentry
    $userData = array(
        'email' => $sanitizedData['email'],
        'password' => $sanitizedData['password'],
    );

    try {
        $user = Sentry::getUserProvider()->create($userData);

        // Add them to the proper group
        $adminGroup = Sentry::getGroupProvider()->findByName('Speakers');
        $user->addGroup($adminGroup);

        // Create a Speaker record
        $speaker = new \OpenCFP\Speaker($db);
        $speaker->create(array(
            $user->getId(),
            $sanitizedData['speaker_info'])
        );

        $pageTemplate = "create_user_success.twig";
        $form->sendActivationMessage($user, $container, $twig);
    } catch (Cartalyst\Sentry\Users\UserExistsException $e) {
        $data['error_messages'] = array("A user already exists with that email address");
    }
}

if (!$valid && empty($data['error_messages'])) {
    $data['error_messages'] = $form->errorMessages;
}

$template = $twig->loadTemplate($pageTemplate);
$template->display($data);
