import SessionSecurityController from './SessionSecurityController'
import CloudPasswordController from './CloudPasswordController'

const Auth = {
    SessionSecurityController: Object.assign(SessionSecurityController, SessionSecurityController),
    CloudPasswordController: Object.assign(CloudPasswordController, CloudPasswordController),
}

export default Auth