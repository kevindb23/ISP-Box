class LoginValidator
{
    public static function validate($input)
    {
        if (empty($input['username']) || empty($input['password'])) {
            throw new \Exception("Missing credentials");
        }
    }
}
