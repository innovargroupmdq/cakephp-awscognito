# AwsCognito plugin for CakePHP

Cognito Integration Status:
- new users will be mirrored in Cognito
- if these users are deleted, they will be deleted on Cognito as well
- if these users are deactivated (active = 0), they will be disabled on Cognito
- if these users get reactivated (active = 1), they will be enabled on Cognito
- if the user is created deactivated (active = 0), it will attempt to disable it on cognito after creating it,
    but if this fails, consistency will be prioritized and the user will be created activated.
- if the email is modified, it will be updated on Cognito
    it will also be unverified
- passwords can be reset from the admin panel
- the invitation email can be resend manually if needed

IMPORTANT NOTES:
- always ensure the password policies match between cognito and the app.
- always ensure the required fields match between cognito and the app.
- keep cognito for authentication only: don't add unnecessary fields to the cognito user pool.
- don't let users change their email or phone from within cognito. Always make changes through the API, and let the API update Cognito.


TO-DO:
-make a setting for autoverified emails in new users and in edited emails
-review expiration time limits for new accounts


