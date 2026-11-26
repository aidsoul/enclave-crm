import AuthController from './AuthController'
import Auth from './Auth'
import OrderController from './OrderController'
import OrderCommentController from './OrderCommentController'
import OrderMessageController from './OrderMessageController'
import PersonnelController from './PersonnelController'
import SiteController from './SiteController'
import CustomerController from './CustomerController'
import ProductController from './ProductController'
import LeadController from './LeadController'
import DashboardController from './DashboardController'
import CourierHandoverController from './CourierHandoverController'
import ExportController from './ExportController'
import SettingsController from './SettingsController'
import EmergencyWipeController from './EmergencyWipeController'
import AuditController from './AuditController'
import InvitationController from './InvitationController'
import ExternalLinkController from './ExternalLinkController'
import TmaController from './TmaController'

const Controllers = {
    AuthController: Object.assign(AuthController, AuthController),
    Auth: Object.assign(Auth, Auth),
    OrderController: Object.assign(OrderController, OrderController),
    OrderCommentController: Object.assign(OrderCommentController, OrderCommentController),
    OrderMessageController: Object.assign(OrderMessageController, OrderMessageController),
    PersonnelController: Object.assign(PersonnelController, PersonnelController),
    SiteController: Object.assign(SiteController, SiteController),
    CustomerController: Object.assign(CustomerController, CustomerController),
    ProductController: Object.assign(ProductController, ProductController),
    LeadController: Object.assign(LeadController, LeadController),
    DashboardController: Object.assign(DashboardController, DashboardController),
    CourierHandoverController: Object.assign(CourierHandoverController, CourierHandoverController),
    ExportController: Object.assign(ExportController, ExportController),
    SettingsController: Object.assign(SettingsController, SettingsController),
    EmergencyWipeController: Object.assign(EmergencyWipeController, EmergencyWipeController),
    AuditController: Object.assign(AuditController, AuditController),
    InvitationController: Object.assign(InvitationController, InvitationController),
    ExternalLinkController: Object.assign(ExternalLinkController, ExternalLinkController),
    TmaController: Object.assign(TmaController, TmaController),
}

export default Controllers