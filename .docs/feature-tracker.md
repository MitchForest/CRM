AUTH:

We are successfully using JWT OAuth2
  authentication. Here's what's happening:

  1. OAuth2 Password Grant Flow: We're using the
  OAuth2 password grant type with JWT tokens
  2. JWT Access Token: The long encoded string
  starting with
  eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9... is a JWT
  (JSON Web Token)
  3. Bearer Authentication: We use the JWT as a Bearer
   token in the Authorization header
  4. Token Structure: The JWT is signed with RSA256
  using the private/public key pair we generated

  The authentication flow works like this:
  - Client sends username/password to
  /Api/access_token
  - Server validates credentials and returns a JWT
  access token + refresh token
  - Client includes the JWT in subsequent requests as
  Authorization: Bearer <token>
  - Server validates the JWT signature using the
  public key

  The JWT contains:
  - User ID (sub claim)
  - Client ID (aud claim)
  - Expiration time (exp claim - 1 hour)
  - Issue time (iat claim)