## KEY DECESIONS MADE:

### Phase 1 Backend Implementation (2025-07-23)

1. **Architecture: Complete Separation of Custom Code from SuiteCRM Core**
   - **Decision**: Keep ALL customizations outside the SuiteCRM directory
   - **Rationale**: Enables clean SuiteCRM upgrades without losing customizations
   - **Implementation**:
     - `/backend/suitecrm/` - Pure SuiteCRM installation (untouched)
     - `/backend/custom-api/` - Custom API implementations
     - `/backend/custom-extensions/` - SuiteCRM extensions (fields, language)
     - `/backend/config/` - Configuration overrides
   - **Impact**: Future SuiteCRM updates can be done by simply replacing the suitecrm folder

2. **Authentication: JWT with OAuth2 Password Grant**
   - **Decision**: Use SuiteCRM's built-in v8 API with OAuth2 + JWT tokens
   - **Rationale**: Industry standard, stateless, secure, built-in refresh mechanism
   - **Implementation**:
     - OAuth2 password grant flow at `/Api/access_token`
     - JWT tokens with 1-hour expiry
     - Refresh tokens with 7-day expiry
     - RSA256 signing with generated key pair
   - **Deviation**: Original plan mentioned JWT but didn't specify OAuth2 flow

3. **Custom Fields Implementation**
   - **Decision**: Use SuiteCRM Extension framework instead of direct database modifications
   - **Rationale**: Upgrade-safe, follows SuiteCRM best practices
   - **Implementation**:
     - Fields defined in `/custom/Extension/modules/{Module}/Ext/Vardefs/`
     - Language labels in `/custom/Extension/modules/{Module}/Ext/Language/`
     - Applied via Quick Repair and Rebuild
   - **Fields Added**:
     - Leads: `ai_score`, `ai_score_date`, `ai_insights`
     - Accounts: `health_score`, `mrr`, `last_activity`

4. **API Structure: Use Existing v8 Endpoints**
   - **Decision**: Leverage SuiteCRM's v8 REST API instead of building custom endpoints
   - **Rationale**: Maintains compatibility, reduces code maintenance, follows JSON:API spec
   - **Endpoints**:
     - `POST /Api/access_token` - Authentication
     - `GET /Api/V8/module/{module}` - List records
     - `GET /Api/V8/module/{module}/{id}` - Get single record
     - `POST /Api/V8/module` - Create record
     - `PATCH /Api/V8/module` - Update record
     - `DELETE /Api/V8/module/{module}/{id}` - Delete record
   - **Deviation**: Original plan suggested custom endpoints, but v8 API provides everything needed

5. **Docker Architecture Adjustments**
   - **Decision**: Mount custom directories as volumes instead of copying into container
   - **Rationale**: Faster development, easier debugging, preserves separation
   - **Implementation**:
     ```yaml
     volumes:
       - ./backend/suitecrm:/var/www/html:rw
       - ./backend/custom-api:/var/www/html/custom/api:rw
       - ./backend/custom-extensions:/var/www/html/custom/Extension:rw
       - ./backend/config/config.php:/var/www/html/config.php:rw
       - ./backend/config/config_override.php:/var/www/html/config_override.php:rw
     ```

6. **Redis Integration**
   - **Decision**: Add Redis container for caching (not in original docker-compose)
   - **Rationale**: Improved performance for API responses
   - **Implementation**: Added Redis service, configured in config_override.php

7. **Fresh Installation Approach**
   - **Decision**: Clone from MitchForest/CRM hotfix branch instead of official SuiteCRM
   - **Rationale**: Contains necessary fixes for v8 API functionality
   - **Impact**: Avoided several initialization issues present in vanilla SuiteCRM

8. **API-First Development**
   - **Decision**: Focus entirely on v8 REST API, ignore SuiteCRM UI
   - **Rationale**: Headless architecture as specified, cleaner separation of concerns
   - **Impact**: No time spent on SuiteCRM theme/UI customizations

9. **Simplified User Management**
   - **Decision**: Create dedicated API user instead of using admin
   - **Rationale**: Better security, easier to track API usage
   - **Implementation**: Created `apiuser` with admin privileges for API access

10. **CORS Configuration**
    - **Decision**: Configure CORS at both Apache and SuiteCRM levels
    - **Rationale**: Maximum compatibility with frontend development
    - **Allowed Origins**: localhost:3000 (React), localhost:5173 (Vite)

These decisions prioritized:
- **Upgradeability**: Clean separation allows easy SuiteCRM updates
- **Maintainability**: Using built-in APIs reduces custom code
- **Security**: OAuth2/JWT provides industry-standard authentication
- **Performance**: Redis caching improves response times
- **Developer Experience**: Volume mounts enable rapid development