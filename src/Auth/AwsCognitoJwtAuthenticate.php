<?php
namespace EvilCorp\AwsCognito\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Http\ServerRequest;
use Cake\Http\Response;

use Cake\Network\Exception\UnauthorizedException;
use Lcobucci\JWT\Parser as JWTParser;

class AwsCognitoJwtAuthenticate extends BaseAuthenticate
{

    public function authenticate(ServerRequest $request, Response $response)
    {
        return $this->getUser($request);
    }

    public function getUser(ServerRequest $request)
    {
        //no need to verify the validity of the token cause aws guarantees it
        $jwt_string = $request->getHeaderLine('Authorization');
        if(empty($jwt_string)) return false;

        try {
            $jwt_parser = new JWTParser();
            $jwt = $jwt_parser->parse((string) $jwt_string); // Parses from a string
            $user = $jwt->getClaims();

            $cognito_id_field = 'sub';

            if(empty($user[$cognito_id_field])) return false;
            if(!method_exists($user[$cognito_id_field], 'getValue')) return false;

            $cognito_id = $user[$cognito_id_field]->getValue();

            $this->setConfig('fields.username', 'aws_cognito_id');

            return $this->_findUser($cognito_id);

        } catch (Exception $e) {
            return false;
        }
    }

    public function unauthenticated(ServerRequest $request, Response $response)
    {
        throw new UnauthorizedException();
    }

}
