import { computed, ref } from 'vue';

const STORAGE_KEY = 'flashcard.locale';
const supportedLocales = ['fa', 'en'];

function initialLocale() {
    try {
        const storedLocale = window.localStorage.getItem(STORAGE_KEY);
        return supportedLocales.includes(storedLocale) ? storedLocale : 'fa';
    } catch {
        return 'fa';
    }
}

export const locale = ref(initialLocale());
export const isRtl = computed(() => locale.value === 'fa');

export const messages = {
    fa: {
        app: { name: 'فلش‌کارت', subtitle: 'سیستم مطالعه لایتنر' },
        language: { label: 'زبان', fa: 'فارسی', en: 'English' },
        common: {
            add: 'افزودن', save: 'ذخیره', cancel: 'انصراف', close: 'بستن', delete: 'حذف', edit: 'ویرایش',
            refresh: 'تازه‌سازی', previous: 'قبلی', next: 'بعدی', go: 'برو', submit: 'ثبت', loading: 'در حال بارگذاری...',
            name: 'نام', email: 'ایمیل', password: 'رمز عبور', title: 'عنوان', type: 'نوع', active: 'فعال', inactive: 'غیرفعال',
            roles: 'نقش‌ها', noRole: 'بدون نقش', fromToTotal: 'نمایش {from} تا {to} از {total}', pageProgress: '{current} از {total}',
            card: 'کارت', cards: '{count} کارت', step: 'گام {number}', side: 'روی {number}', audioFile: 'فایل صدا',
            noMedia: 'رسانه‌ای برای این رو ثبت نشده است.', customStep: 'گام دلخواه', selectCustomStep: 'انتخاب گام دلخواه',
            theme: 'تم: {theme}', light: 'روشن', dark: 'تاریک', system: 'سیستم', menu: 'منو', back: 'بازگشت',
        },
        nav: { categories: 'دسته‌ها', users: 'کاربران', profile: 'پروفایل', logout: 'خروج' },
        auth: {
            title: 'ورود به فلش‌کارت', hint: 'ایمیل و رمز عبور را وارد کنید.', submit: 'ورود', submitting: 'در حال ورود...',
            failed: 'ورود انجام نشد.', invalidCredentials: 'ایمیل یا رمز عبور صحیح نیست.', inactive: 'این حساب غیرفعال است.',
        },
        categories: {
            title: 'دسته‌ها', add: 'افزودن دسته', new: 'دسته جدید', name: 'نام دسته', parent: 'زیرمجموعه', noParent: 'بدون والد',
            loading: 'در حال دریافت دسته‌ها...', fetchFailed: 'دسته‌ها دریافت نشدند.', saved: 'دسته اضافه شد.', saveFailed: 'ذخیره دسته انجام نشد.',
            directCards: '{count} کارت مستقیم', progress: 'پیشرفت {percent} درصد', stepStatus: 'وضعیت گام‌ها', due: '{count} آماده',
            unintroducedTitle: 'هنوز وارد برنامهٔ مطالعه نشده: {count}', unintroducedLabel: '{count} کارت هنوز وارد برنامهٔ مطالعه نشده',
            dueTodayTitle: 'آمادهٔ مطالعهٔ امروز: {count}', dueTodayLabel: '{count} کارت آمادهٔ مطالعهٔ امروز', noAccess: 'بدون دسترسی',
            back: 'بازگشت به دسته‌ها', loadingInfo: 'در حال دریافت اطلاعات...', fetchInfoFailed: 'اطلاعات دسته دریافت نشد.',
            editCards: 'ویرایش کارت‌ها', editSteps: 'ویرایش گام‌ها', noStepCards: 'کارت‌های بدون گام',
            unintroducedCount: '{count} کارت هنوز وارد برنامه مطالعه نشده است.', introduce: 'انتقال به گام ۰', introduceFailed: 'کارت‌ها وارد گام صفر نشدند.',
            startStudy: 'شروع مطالعه', noCardsToday: 'کارتی برای امروز نیست', viewStepCards: 'مشاهده کارت‌های گام', noCardsInStep: 'کارتی در این گام نیست',
            waiting: 'در انتظار موعد', ready: 'آماده مطالعه', totalCards: 'کل کارت‌ها', deleteConfirm: 'این دسته حذف شود؟', deleteFailed: 'حذف دسته انجام نشد.',
            cardsTitle: 'کارت‌های {name}', backToSteps: 'بازگشت به گام‌ها',
            notEmpty: 'این دسته زیرمجموعه یا کارت دارد و قابل حذف نیست.',
        },
        steps: {
            onlyLastRemoval: 'فقط گام انتهایی را حذف کنید تا کارت‌ها جابه‌جا نشوند.', occupied: 'این گام کارت دارد و قابل حذف نیست.',
            minimum: 'حداقل یک گام زمان‌دار لازم است.', add: 'افزودن گام', remove: 'حذف گام', day: 'روز',
            unlimited: 'بدون محدودیت روز', delayDays: '{count} روز فاصله', saveFailed: 'گام‌ها ذخیره نشدند.', invalid: 'گام انتخابی معتبر نیست.',
        },
        cards: {
            manage: 'مدیریت کارت‌ها', add: 'افزودن کارت', loading: 'در حال دریافت کارت‌ها...', fetchFailed: 'دریافت کارت‌ها انجام نشد.',
            englishActive: 'انگلیسی اکتیو', englishPassive: 'انگلیسی پسیو', otherType: 'سایر',
            empty: 'هنوز کارتی برای این دسته ثبت نشده است.', fallbackTitle: 'کارت {id}', sides: '{count} رو', preview: 'پیش‌نمایش',
            new: 'کارت جدید', edit: 'ویرایش کارت', single: 'تکی', smart: 'چنتایی هوشمند', normal: 'چنتایی معمولی',
            saveFailed: 'ذخیره کارت انجام نشد.', created: 'کارت اضافه شد.', updated: 'کارت ویرایش شد.', deleted: 'کارت حذف شد.', deleteFailed: 'حذف کارت انجام نشد.',
            deleteConfirm: 'این کارت حذف شود؟', addCards: 'افزودن کارت‌ها', side: 'رو', addSide: 'افزودن رو', removeSide: 'حذف رو', text: 'متن',
            imageLinks: 'لینک تصاویر', audioLinks: 'لینک ویس‌ها', imageFile: 'فایل تصویر', voiceFile: 'فایل ویس', oneWordPerLine: 'هر خط یک کلمه',
            wordCount: '{count} کلمه', rowCount: '{count} ردیف', generatedCount: '{count} کارت ساخته می‌شود.', textRequired: 'حداقل یک کارت با متن وارد کنید.',
            tooMany: 'در هر بار حداکثر ۵۰۰ کارت قابل ساخت است.',
            bulkCreated: '{count} کارت اضافه شد.', bulkFailed: 'افزودن چندتایی کارت‌ها انجام نشد.', smartQueuedCount: '{count} کارت به صف پردازش هوشمند اضافه شد.',
            smartBulkFailed: 'افزودن کارت‌های هوشمند انجام نشد.', smartQueued: 'کارت به صف پردازش هوشمند اضافه شد.', smartFailed: 'پردازش هوشمند انجام نشد.',
            processing: 'درحال پردازش هوشمند', queued: 'در صف پردازش هوشمند', processingFailed: 'پردازش ناموفق', inQueue: 'در صف', smartProcess: 'پردازش هوشمند', cardPreview: 'پیش‌نمایش کارت',
        },
        study: {
            back: 'بازگشت به گام‌ها', preparing: 'در حال آماده‌سازی مطالعه...', fetchFailed: 'کارت‌های مطالعه دریافت نشدند.', saveFailed: 'ثبت نتیجه انجام نشد.',
            mastered: 'کاملا بلدم', known: 'بلدم', unknown: 'بلد نیستم', previousSide: 'روی قبلی', nextSide: 'روی بعدی', goToSide: 'رفتن به روی {number}',
            noDueDate: 'بدون موعد', stepCardsLoading: 'در حال دریافت کارت‌ها...', stepCardsFailed: 'کارت‌های این گام دریافت نشدند.', emptyStep: 'کارتی در این گام نیست.',
            nextCard: 'کارت بعد', previousCard: 'کارت قبل', rescheduleHint: 'بعد از ثبت، موعد کارت بر اساس گام جدید دوباره محاسبه می‌شود.', moveFailed: 'تغییر گام انجام نشد.',
        },
        profile: {
            title: 'پروفایل', general: 'عمومی', security: 'امنیت', currentPassword: 'رمز فعلی', newPassword: 'رمز جدید', confirmPassword: 'تکرار رمز جدید',
            save: 'ذخیره', changePassword: 'تغییر رمز', saved: 'اطلاعات عمومی ذخیره شد.', saveFailed: 'ذخیره اطلاعات انجام نشد.',
            passwordChanged: 'رمز عبور تغییر کرد.', passwordFailed: 'تغییر رمز انجام نشد.',
        },
        users: {
            title: 'مدیریت کاربران', add: 'افزودن کاربر', new: 'کاربر جدید', loading: 'در حال دریافت کاربران...', fetchFailed: 'دریافت کاربران انجام نشد.',
            empty: 'کاربری وجود ندارد.', activeLabel: 'فعال باشد', saved: 'کاربر اضافه شد.', saveFailed: 'ذخیره کاربر انجام نشد.',
            activated: 'کاربر فعال شد.', deactivated: 'کاربر غیرفعال شد.', statusFailed: 'تغییر وضعیت کاربر انجام نشد.',
            cannotDeleteSelf: 'حساب خودتان قابل حذف نیست.', deleted: 'کاربر حذف شد.', deleteFailed: 'حذف کاربر انجام نشد.',
            back: 'بازگشت به کاربران', loadingUser: 'در حال دریافت اطلاعات کاربر...', edit: 'ویرایش کاربر', info: 'اطلاعات کاربر', newPassword: 'رمز جدید',
            saveUser: 'ذخیره کاربر', userSaved: 'اطلاعات کاربر ذخیره شد.', fetchUserFailed: 'دریافت اطلاعات کاربر انجام نشد.',
            accesses: 'دسترسی دسته‌ها', saveAccess: 'ذخیره دسترسی', accessSaved: 'دسترسی دسته‌ها ذخیره شد.', accessSaveFailed: 'ذخیره دسترسی دسته‌ها انجام نشد.',
            globalAccessHint: 'این کاربر نقش accessAllCategories دارد؛ برای گرفتن دسترسی کامل به دسته‌ها، این نقش را از بخش نقش‌ها بردارید.',
            access: 'دسترسی', editAccess: 'ویرایش', noCategories: 'دسته‌ای وجود ندارد.',
        },
        roles: { manageUser: 'مدیریت کاربران', accessAllCategories: 'دسترسی به همه دسته‌ها' },
        errors: {
            generic: 'خطایی رخ داد. دوباره تلاش کنید.', forbidden: 'اجازه انجام این عملیات را ندارید.', notFound: 'مورد درخواستی پیدا نشد.',
            validation: 'اطلاعات واردشده معتبر نیست.', required: 'فیلد {field} الزامی است.', email: 'فرمت {field} معتبر نیست.',
            unique: '{field} قبلاً استفاده شده است.', minString: '{field} باید حداقل {min} نویسه باشد.', confirmed: 'تکرار {field} مطابقت ندارد.',
            invalid: 'مقدار انتخاب‌شده برای {field} معتبر نیست.', currentPassword: 'رمز فعلی درست نیست.',
            deactivateSelf: 'نمی‌توانید حساب خودتان را غیرفعال کنید.', deleteSelf: 'نمی‌توانید حساب خودتان را حذف کنید.',
            lastManager: 'آخرین مدیر فعال کاربران را نمی‌توان غیرفعال کرد یا نقش مدیریت او را برداشت.',
            smartType: 'پردازش هوشمند فقط برای کارت‌های انگلیسی فعال و انگلیسی پسیو فعال است.',
        },
        fields: { name: 'نام', email: 'ایمیل', password: 'رمز عبور', current_password: 'رمز فعلی', title: 'عنوان', type: 'نوع', sides: 'روها', roles: 'نقش‌ها', accesses: 'دسترسی‌ها' },
    },
    en: {
        app: { name: 'FlashCard', subtitle: 'Leitner study system' },
        language: { label: 'Language', fa: 'فارسی', en: 'English' },
        common: {
            add: 'Add', save: 'Save', cancel: 'Cancel', close: 'Close', delete: 'Delete', edit: 'Edit', refresh: 'Refresh', previous: 'Previous', next: 'Next', go: 'Go', submit: 'Submit', loading: 'Loading...',
            name: 'Name', email: 'Email', password: 'Password', title: 'Title', type: 'Type', active: 'Active', inactive: 'Inactive', roles: 'Roles', noRole: 'No roles',
            fromToTotal: 'Showing {from} to {to} of {total}', pageProgress: '{current} of {total}', card: 'Card', cards: '{count} cards', step: 'Step {number}', side: 'Side {number}', audioFile: 'Audio file',
            noMedia: 'No media has been added to this side.', customStep: 'Custom step', selectCustomStep: 'Select a custom step', theme: 'Theme: {theme}', light: 'Light', dark: 'Dark', system: 'System', menu: 'Menu', back: 'Back',
        },
        nav: { categories: 'Categories', users: 'Users', profile: 'Profile', logout: 'Log out' },
        auth: { title: 'Sign in to FlashCard', hint: 'Enter your email and password.', submit: 'Sign in', submitting: 'Signing in...', failed: 'Sign-in failed.', invalidCredentials: 'The email or password is incorrect.', inactive: 'This account is inactive.' },
        categories: {
            title: 'Categories', add: 'Add category', new: 'New category', name: 'Category name', parent: 'Parent category', noParent: 'No parent', loading: 'Loading categories...', fetchFailed: 'Could not load categories.',
            saved: 'Category added.', saveFailed: 'Could not save the category.', directCards: '{count} direct cards', progress: '{percent}% progress', stepStatus: 'Step status', due: '{count} due',
            unintroducedTitle: 'Not added to the study plan: {count}', unintroducedLabel: '{count} cards have not been added to the study plan', dueTodayTitle: 'Due for study today: {count}', dueTodayLabel: '{count} cards are due for study today', noAccess: 'No access',
            back: 'Back to categories', loadingInfo: 'Loading details...', fetchInfoFailed: 'Could not load category details.', editCards: 'Edit cards', editSteps: 'Edit steps', noStepCards: 'Cards without a step',
            unintroducedCount: '{count} cards have not been added to the study plan.', introduce: 'Move to step 0', introduceFailed: 'Could not move cards to step zero.', startStudy: 'Start studying', noCardsToday: 'No cards due today',
            viewStepCards: 'View cards in this step', noCardsInStep: 'There are no cards in this step', waiting: 'Waiting until due', ready: 'Ready to study', totalCards: 'Total cards', deleteConfirm: 'Delete this category?', deleteFailed: 'Could not delete the category.',
            cardsTitle: '{name} cards', backToSteps: 'Back to steps',
            notEmpty: 'This category contains subcategories or cards and cannot be deleted.',
        },
        steps: { onlyLastRemoval: 'Remove only the last step to avoid moving cards.', occupied: 'This step contains cards and cannot be removed.', minimum: 'At least one timed step is required.', add: 'Add step', remove: 'Remove step', day: 'days', unlimited: 'No day limit', delayDays: '{count}-day interval', saveFailed: 'Could not save the steps.', invalid: 'The selected step is invalid.' },
        cards: {
            manage: 'Manage cards', add: 'Add card', loading: 'Loading cards...', fetchFailed: 'Could not load cards.', empty: 'No cards have been added to this category.', fallbackTitle: 'Card {id}', sides: '{count} sides', preview: 'Preview',
            englishActive: 'English Active', englishPassive: 'English Passive', otherType: 'Other',
            new: 'New card', edit: 'Edit card', single: 'Single', smart: 'Smart bulk', normal: 'Regular bulk', saveFailed: 'Could not save the card.', created: 'Card added.', updated: 'Card updated.', deleted: 'Card deleted.', deleteFailed: 'Could not delete the card.',
            deleteConfirm: 'Delete this card?', addCards: 'Add cards', side: 'Side', addSide: 'Add side', removeSide: 'Remove side', text: 'Text', imageLinks: 'Image links', audioLinks: 'Audio links', imageFile: 'Image file', voiceFile: 'Audio file',
            oneWordPerLine: 'One word per line', wordCount: '{count} words', rowCount: '{count} rows', generatedCount: '{count} cards will be created.', textRequired: 'Enter text for at least one card.', bulkCreated: '{count} cards added.', bulkFailed: 'Could not add the cards.',
            tooMany: 'A maximum of 500 cards may be created at once.',
            smartQueuedCount: '{count} cards added to the smart-processing queue.', smartBulkFailed: 'Could not add smart cards.', smartQueued: 'Card added to the smart-processing queue.', smartFailed: 'Smart processing failed.',
            processing: 'Smart processing', queued: 'Queued for smart processing', processingFailed: 'Processing failed', inQueue: 'Queued', smartProcess: 'Smart process', cardPreview: 'Card preview',
        },
        study: {
            back: 'Back to steps', preparing: 'Preparing your study session...', fetchFailed: 'Could not load study cards.', saveFailed: 'Could not save the result.', mastered: 'Mastered', known: 'I know it', unknown: "I don't know it", previousSide: 'Previous side', nextSide: 'Next side', goToSide: 'Go to side {number}',
            noDueDate: 'No due date', stepCardsLoading: 'Loading cards...', stepCardsFailed: 'Could not load cards for this step.', emptyStep: 'There are no cards in this step.', nextCard: 'Next card', previousCard: 'Previous card',
            rescheduleHint: 'After saving, the card due date will be recalculated for the new step.', moveFailed: 'Could not move the card.',
        },
        profile: { title: 'Profile', general: 'General', security: 'Security', currentPassword: 'Current password', newPassword: 'New password', confirmPassword: 'Confirm new password', save: 'Save', changePassword: 'Change password', saved: 'Profile details saved.', saveFailed: 'Could not save profile details.', passwordChanged: 'Password changed.', passwordFailed: 'Could not change the password.' },
        users: {
            title: 'Manage users', add: 'Add user', new: 'New user', loading: 'Loading users...', fetchFailed: 'Could not load users.', empty: 'No users found.', activeLabel: 'Active', saved: 'User added.', saveFailed: 'Could not save the user.',
            activated: 'User activated.', deactivated: 'User deactivated.', statusFailed: 'Could not change user status.', cannotDeleteSelf: 'You cannot delete your own account.', deleted: 'User deleted.', deleteFailed: 'Could not delete the user.',
            back: 'Back to users', loadingUser: 'Loading user details...', edit: 'Edit user', info: 'User details', newPassword: 'New password', saveUser: 'Save user', userSaved: 'User details saved.', fetchUserFailed: 'Could not load user details.',
            accesses: 'Category access', saveAccess: 'Save access', accessSaved: 'Category access saved.', accessSaveFailed: 'Could not save category access.', globalAccessHint: 'This user has the accessAllCategories role. Remove it in Roles to manage category access individually.',
            access: 'Access', editAccess: 'Edit', noCategories: 'No categories found.',
        },
        roles: { manageUser: 'Manage users', accessAllCategories: 'Access all categories' },
        errors: {
            generic: 'Something went wrong. Please try again.', forbidden: 'You do not have permission to perform this action.', notFound: 'The requested item was not found.', validation: 'The submitted information is invalid.',
            required: 'The {field} field is required.', email: 'The {field} must be a valid email address.', unique: 'The {field} has already been taken.', minString: 'The {field} must be at least {min} characters.',
            confirmed: 'The {field} confirmation does not match.', invalid: 'The selected {field} is invalid.', currentPassword: 'The current password is incorrect.', deactivateSelf: 'You cannot deactivate your own account.', deleteSelf: 'You cannot delete your own account.',
            smartType: 'Smart processing is only available for active and passive English cards.',
            lastManager: 'The last active user manager cannot be deactivated or lose the manage-user role.',
        },
        fields: { name: 'name', email: 'email', password: 'password', current_password: 'current password', title: 'title', type: 'type', sides: 'sides', roles: 'roles', accesses: 'accesses' },
    },
};

function lookup(targetLocale, key) {
    return key.split('.').reduce((value, part) => value?.[part], messages[targetLocale]);
}

export function t(key, params = {}) {
    const template = lookup(locale.value, key) ?? lookup('fa', key) ?? key;
    if (typeof template !== 'string') return key;
    return template.replace(/\{(\w+)\}/g, (_, name) => params[name] ?? `{${name}}`);
}

export function setLocale(value) {
    if (!supportedLocales.includes(value)) return;
    locale.value = value;
    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // The selected language still works for this session when storage is unavailable.
    }
    applyDocumentLocale();
}

export function applyDocumentLocale() {
    document.documentElement.lang = locale.value;
    document.documentElement.dir = isRtl.value ? 'rtl' : 'ltr';
    document.title = t('app.name');
}

export function roleLabel(role) {
    return t(`roles.${role}`);
}

function fieldLabel(field = '') {
    const root = String(field).split('.')[0];
    return t(`fields.${root}`);
}

function translateValidationMessage(message) {
    let match;
    if ((match = message.match(/^The (.+) field is required\.?$/i))) return t('errors.required', { field: fieldLabel(match[1]) });
    if ((match = message.match(/^The (.+?)(?: field)? must be a valid email address\.?$/i))) return t('errors.email', { field: fieldLabel(match[1]) });
    if ((match = message.match(/^The (.+) has already been taken\.?$/i))) return t('errors.unique', { field: fieldLabel(match[1]) });
    if ((match = message.match(/^The (.+?)(?: field)? must be at least (\d+) characters\.?$/i))) return t('errors.minString', { field: fieldLabel(match[1]), min: match[2] });
    if ((match = message.match(/^The (.+?)(?: field)? confirmation does not match\.?$/i))) return t('errors.confirmed', { field: fieldLabel(match[1]) });
    if ((match = message.match(/^The selected (.+) is invalid\.?$/i))) return t('errors.invalid', { field: fieldLabel(match[1]) });
    return null;
}

const apiMessageKeys = {
    'Invalid credentials.': 'auth.invalidCredentials',
    'This account is inactive.': 'auth.inactive',
    'The current password is incorrect.': 'errors.currentPassword',
    'You cannot deactivate your own account.': 'errors.deactivateSelf',
    'You cannot delete your own account.': 'errors.deleteSelf',
    'The last active user manager cannot be deactivated or lose the manage-user role.': 'errors.lastManager',
    'Smart processing is only available for active and passive English cards.': 'errors.smartType',
    'This category contains subcategories or cards and cannot be deleted.': 'categories.notEmpty',
    'At least one timed step is required.': 'steps.minimum',
    'Only trailing steps may be removed to prevent cards from moving.': 'steps.onlyLastRemoval',
    'You cannot remove a step that contains cards.': 'steps.occupied',
    'The selected step does not exist.': 'steps.invalid',
    'The selected step is invalid.': 'steps.invalid',
    'Enter at least one line.': 'cards.textRequired',
    'Enter at least one line to create cards.': 'cards.textRequired',
    'Enter text for at least one card.': 'cards.textRequired',
    'A maximum of 500 cards may be created at once.': 'cards.tooMany',
    'OPENAI_API_KEY is not configured.': 'cards.smartFailed',
    'A card must have a title or content on its first side before smart processing.': 'cards.smartFailed',
    'Could not generate text with OpenAI.': 'cards.smartFailed',
    'The OpenAI text output could not be read.': 'cards.smartFailed',
    'Could not generate pronunciation audio.': 'cards.smartFailed',
    'Could not generate the educational image.': 'cards.smartFailed',
    'The OpenAI image output could not be saved.': 'cards.smartFailed',
};

export function apiError(exception, fallbackKey = 'errors.generic') {
    const status = exception?.response?.status;
    const data = exception?.response?.data || {};
    const message = Object.values(data.errors || {}).flat()[0] || data.message;
    const knownKey = apiMessageKeys[message];

    if (knownKey) return t(knownKey);
    if (message) {
        const validation = translateValidationMessage(message);
        if (validation) return validation;
        if (locale.value === 'en') return message;
    }
    if (status === 403) return t('errors.forbidden');
    if (status === 404) return t('errors.notFound');
    if (status === 422 && !fallbackKey) return t('errors.validation');
    return t(fallbackKey || 'errors.generic');
}

applyDocumentLocale();
