<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\Contact;
use Cardy\WebUI\Controller;

class ContactsController extends Controller
{
    // -------------------------------------------------------
    // Active address book session helpers
    // -------------------------------------------------------

    private function getActiveAddressBookId(string $username): ?int
    {
        $key = 'active_ab_' . $username;
        if (!empty($_SESSION[$key])) {
            $id    = (int) $_SESSION[$key];
            $books = Contact::getAllAddressBooksForUser($username);
            foreach ($books as $book) {
                if ((int) $book['id'] === $id) {
                    return $id;
                }
            }
            unset($_SESSION[$key]);
        }
        $id = Contact::getAddressBookId($username);
        if ($id !== null) {
            $_SESSION[$key] = $id;
        }
        return $id;
    }

    private function setActiveAddressBookId(string $username, int $id): void
    {
        $_SESSION['active_ab_' . $username] = $id;
    }

    // -------------------------------------------------------
    // Contact list
    // -------------------------------------------------------

    public function index(): void
    {
        $user        = $this->requireAuth();
        $search      = trim($_GET['q'] ?? '');
        $sort        = trim((string) ($_GET['sort'] ?? 'default'));
        $category    = trim((string) ($_GET['category'] ?? 'all'));
        $groupFilter = trim((string) ($_GET['group'] ?? ''));
        $starredOnly = ($_GET['starred'] ?? '') === '1';

        $allowedSorts      = ['default', 'first_name', 'last_name', 'birthday', 'organization', 'recently_updated'];
        $allowedCategories = ['all', 'people', 'business'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'default';
        }
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'all';
        }
        if ($groupFilter !== '' && !ctype_digit($groupFilter)) {
            $groupFilter = '';
        }

        $allAddressBooks = Contact::getAllAddressBooksForUser($user['username']);
        $activeAbId      = $this->getActiveAddressBookId($user['username']);
        $activeBook      = null;
        foreach ($allAddressBooks as $book) {
            if ((int) $book['id'] === $activeAbId) {
                $activeBook = $book;
                break;
            }
        }

        $contacts = Contact::allForUser($user['username'], $search, $sort, $groupFilter, $starredOnly, $activeAbId);
        if ($category !== 'all') {
            $contacts = array_values(array_filter($contacts, function (array $contact) use ($category): bool {
                $isBusiness = $this->isBusinessContact($contact);
                return $category === 'business' ? $isBusiness : !$isBusiness;
            }));
        }

        $allGroups = Contact::getAllGroups($user['username'], $activeAbId);

        $this->render('contacts/index', [
            'user'            => $user,
            'contacts'        => $contacts,
            'allGroups'       => $allGroups,
            'allAddressBooks' => $allAddressBooks,
            'activeBook'      => $activeBook,
            'activeAbId'      => $activeAbId,
            'search'          => $search,
            'sort'            => $sort,
            'category'        => $category,
            'groupFilter'     => $groupFilter,
            'starredOnly'     => $starredOnly,
            'csrf'            => $this->csrfToken(),
            'flash'           => $this->getFlash(),
        ]);
    }

    private function isBusinessContact(array $contact): bool
    {
        $hasPersonName = trim((string) ($contact['first_name'] ?? '')) !== '' || trim((string) ($contact['last_name'] ?? '')) !== '';
        $hasOrg = trim((string) ($contact['org'] ?? '')) !== '';
        return !$hasPersonName && $hasOrg;
    }

    public function view(array $params): void
    {
        $user       = $this->requireAuth();
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        $contact    = Contact::findById((int) $params['id'], $user['username'], $activeAbId);
        if (!$contact) {
            $this->abort(404, 'Contact not found.');
        }

        $history   = Contact::getHistory((int) $contact['db_id']);
        $allGroups = Contact::getAllGroups($user['username'], $activeAbId);

        $this->render('contacts/view', [
            'user'      => $user,
            'contact'   => $contact,
            'history'   => $history,
            'allGroups' => $allGroups,
            'csrf'      => $this->csrfToken(),
        ]);
    }

    public function create(): void
    {
        $user       = $this->requireAuth();
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        $allGroups  = Contact::getAllGroups($user['username'], $activeAbId);
        $this->render('contacts/form', [
            'user'      => $user,
            'contact'   => null,
            'allGroups' => $allGroups,
            'csrf'      => $this->csrfToken(),
        ]);
    }

    public function importForm(): void
    {
        $user = $this->requireAuth();
        $this->render('contacts/import', [
            'user' => $user,
            'csrf' => $this->csrfToken(),
            'flash' => $this->getFlash(),
        ]);
    }

    public function export(): void
    {
        $user       = $this->requireAuth();
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        $format = strtolower(trim($_GET['format'] ?? 'vcf'));
        if (!in_array($format, ['vcf', 'csv', 'icloud_vcf', 'google_csv', 'outlook_csv'], true)) {
            $format = 'vcf';
        }

        $filename = 'contacts-' . date('Y-m-d');

        if ($format === 'vcf' || $format === 'icloud_vcf') {
            $data = Contact::exportAllVCards($user['username'], $activeAbId);
            header('Content-Type: text/vcard; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.vcf"');
            header('Content-Length: ' . strlen($data));
            echo $data;
            exit;
        }

        if ($format === 'google_csv') {
            $data = Contact::exportGoogleCsv($user['username'], $activeAbId);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '-google.csv"');
            header('Content-Length: ' . strlen($data));
            echo $data;
            exit;
        }

        if ($format === 'outlook_csv') {
            $data = Contact::exportOutlookCsv($user['username'], $activeAbId);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '-outlook.csv"');
            header('Content-Length: ' . strlen($data));
            echo $data;
            exit;
        }

        $data = Contact::exportAllCsv($user['username'], $activeAbId);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    }

    public function import(): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $file = $_FILES['import_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Please upload a valid CSV or vCard file.');
            $this->redirect('/contacts/import');
            return;
        }

        $content = (string) file_get_contents((string) $file['tmp_name']);
        if (trim($content) === '') {
            $this->flash('error', 'Uploaded file is empty.');
            $this->redirect('/contacts/import');
            return;
        }

        $isVCard = $this->isVCardUpload($file, $content);
        $isCsv = $this->isCsvUpload($file);

        $activeAbId = $this->getActiveAddressBookId($user['username']);

        try {
            if ($isVCard) {
                $result = Contact::importVCardData($user['username'], $content, $activeAbId);
            } elseif ($isCsv) {
                $result = $this->importCsvContacts($user['username'], $content);
            } else {
                throw new \RuntimeException('Unsupported file type. Please upload a CSV or vCard (.vcf) file.');
            }

            $message = 'Imported ' . $result['imported'] . ' contact(s).';
            if (($result['failed'] ?? 0) > 0) {
                $message .= ' Failed: ' . $result['failed'] . '.';
                if (!empty($result['errors'])) {
                    $message .= ' ' . implode(' | ', array_slice($result['errors'], 0, 2));
                }
            }

            $this->flash(($result['failed'] ?? 0) > 0 ? 'error' : 'success', $message);
        } catch (\Throwable $e) {
            $this->flash('error', 'Import failed: ' . $e->getMessage());
        }

        $this->redirect('/contacts');
    }

    private function isVCardUpload(array $file, string $content): bool
    {
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mimeType = strtolower(trim((string) ($file['type'] ?? '')));

        if (in_array($extension, ['vcf', 'vcard'], true)) {
            return true;
        }

        if (in_array($mimeType, ['text/vcard', 'text/x-vcard', 'text/directory', 'application/vcard', 'application/x-vcard'], true)) {
            return true;
        }

        return stripos($content, 'BEGIN:VCARD') !== false;
    }

    private function isCsvUpload(array $file): bool
    {
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mimeType = strtolower(trim((string) ($file['type'] ?? '')));

        if ($extension === 'csv') {
            return true;
        }

        return in_array($mimeType, ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel'], true);
    }

    public function store(): void
    {
        $user       = $this->requireAuth();
        $this->verifyCsrf();
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        $data       = $this->extractFormData();

        try {
            Contact::create($user['username'], $data, $activeAbId);
            $this->flash('success', 'Contact created successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to create contact: ' . $e->getMessage());
        }

        $this->redirect('/contacts');
    }

    public function edit(array $params): void
    {
        $user       = $this->requireAuth();
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        $contact    = Contact::findById((int) $params['id'], $user['username'], $activeAbId);
        if (!$contact) {
            $this->abort(404, 'Contact not found.');
        }

        $allGroups = Contact::getAllGroups($user['username'], $activeAbId);
        $this->render('contacts/form', [
            'user'      => $user,
            'contact'   => $contact,
            'allGroups' => $allGroups,
            'csrf'      => $this->csrfToken(),
        ]);
    }

    public function update(array $params): void
    {
        $user       = $this->requireAuth();
        $this->verifyCsrf();
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        $contact    = Contact::findById((int) $params['id'], $user['username'], $activeAbId);
        if (!$contact) {
            $this->abort(404, 'Contact not found.');
        }

        $data        = $this->extractFormData();
        $data['uid'] = $contact['uid'];

        try {
            Contact::update((int) $params['id'], $user['username'], $data, $activeAbId);
            $this->flash('success', 'Contact updated successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to update contact: ' . $e->getMessage());
        }

        $this->redirect('/contacts/' . $params['id']);
    }

    public function delete(array $params): void
    {
        $user       = $this->requireAuth();
        $this->verifyCsrf();
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        Contact::delete((int) $params['id'], $user['username'], $activeAbId);
        $this->flash('success', 'Contact deleted.');
        $this->redirect('/contacts');
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function extractFormData(): array
    {
        $post = $_POST;
        $photoUpload = $this->extractPhotoUpload();

        $emails = [];
        $emailAddresses = $post['email'] ?? [];
        $emailTypes = $post['email_type'] ?? [];
        if (!is_array($emailAddresses)) {
            $emailAddresses = [$emailAddresses];
        }
        if (!is_array($emailTypes)) {
            $emailTypes = [$emailTypes];
        }
        foreach ($emailAddresses as $index => $address) {
            $address = trim((string) $address);
            if ($address !== '') {
                $emails[] = [
                    'address' => $address,
                    'type'    => trim((string) ($emailTypes[$index] ?? 'internet')) ?: 'internet',
                ];
            }
        }
        if (count($emails) > 100) {
            throw new \RuntimeException('A maximum of 100 email addresses is allowed per contact.');
        }

        $phones = [];
        $phoneNumbers = $post['phone'] ?? [];
        $phoneTypes = $post['phone_type'] ?? [];
        if (!is_array($phoneNumbers)) {
            $phoneNumbers = [$phoneNumbers];
        }
        if (!is_array($phoneTypes)) {
            $phoneTypes = [$phoneTypes];
        }
        foreach ($phoneNumbers as $index => $number) {
            $number = trim((string) $number);
            if ($number !== '') {
                $phones[] = [
                    'number' => $number,
                    'type'   => trim((string) ($phoneTypes[$index] ?? 'voice')) ?: 'voice',
                ];
            }
        }
        if (count($phones) > 100) {
            throw new \RuntimeException('A maximum of 100 phone numbers is allowed per contact.');
        }

        $addresses = [];
        if (!empty($post['home_street']) || !empty($post['home_city'])) {
            $addresses[] = [
                'type'     => 'home',
                'street'   => $post['home_street']   ?? '',
                'city'     => $post['home_city']     ?? '',
                'region'   => $post['home_region']   ?? '',
                'postcode' => $post['home_postcode']  ?? '',
                'country'  => $post['home_country']  ?? '',
            ];
        }
        if (!empty($post['work_street']) || !empty($post['work_city'])) {
            $addresses[] = [
                'type'     => 'work',
                'street'   => $post['work_street']   ?? '',
                'city'     => $post['work_city']     ?? '',
                'region'   => $post['work_region']   ?? '',
                'postcode' => $post['work_postcode']  ?? '',
                'country'  => $post['work_country']  ?? '',
            ];
        }

        $result = [
            'first_name'  => $post['first_name']  ?? '',
            'last_name'   => $post['last_name']   ?? '',
            'org'         => $post['org']         ?? '',
            'title'       => $post['title']       ?? '',
            'nickname'    => $post['nickname']    ?? '',
            'birthday'    => $post['birthday']    ?? '',
            'note'        => $post['note']        ?? '',
            'is_starred'  => !empty($post['is_starred']),
            'groups'      => array_map('intval', (array) ($post['groups'] ?? [])),
            'emails'      => $emails,
            'phones'      => $phones,
            'addresses'   => $addresses,
            'uid'         => $post['uid']         ?? '',
        ];

        // URLs
        $urlValues = $post['url'] ?? [];
        $urlTypes  = $post['url_type'] ?? [];
        if (!is_array($urlValues)) {
            $urlValues = [$urlValues];
        }
        if (!is_array($urlTypes)) {
            $urlTypes = [$urlTypes];
        }
        $urls = [];
        foreach ($urlValues as $i => $val) {
            $val = trim((string) $val);
            if ($val !== '') {
                $urls[] = [
                    'value' => $val,
                    'type'  => trim((string) ($urlTypes[$i] ?? '')) ?: '',
                ];
            }
        }
        $result['urls'] = $urls;

        // Social profiles
        $spValues = $post['social_value'] ?? [];
        $spTypes  = $post['social_type']  ?? [];
        if (!is_array($spValues)) {
            $spValues = [$spValues];
        }
        if (!is_array($spTypes)) {
            $spTypes = [$spTypes];
        }
        $socialProfiles = [];
        foreach ($spValues as $i => $val) {
            $val = trim((string) $val);
            if ($val !== '') {
                $socialProfiles[] = [
                    'value' => $val,
                    'type'  => strtolower(trim((string) ($spTypes[$i] ?? 'other'))) ?: 'other',
                ];
            }
        }
        $result['social_profiles'] = $socialProfiles;

        // Anniversaries
        $annValues = $post['anniversary'] ?? [];
        if (!is_array($annValues)) {
            $annValues = [$annValues];
        }
        $anniversaries = [];
        foreach ($annValues as $val) {
            $val = trim((string) $val);
            if ($val !== '') {
                $anniversaries[] = $val;
            }
        }
        $result['anniversaries'] = $anniversaries;

        // Custom fields
        $cfLabels = $post['custom_label'] ?? [];
        $cfValues = $post['custom_value'] ?? [];
        if (!is_array($cfLabels)) {
            $cfLabels = [$cfLabels];
        }
        if (!is_array($cfValues)) {
            $cfValues = [$cfValues];
        }
        $customFields = [];
        foreach ($cfValues as $i => $val) {
            $val   = trim((string) $val);
            $label = trim((string) ($cfLabels[$i] ?? '')) ?: 'Custom';
            if ($val !== '') {
                $customFields[] = ['label' => $label, 'value' => $val];
            }
        }
        $result['custom_fields'] = $customFields;

        // Related contacts
        $relNames = $post['related_name'] ?? [];
        $relTypes = $post['related_type'] ?? [];
        if (!is_array($relNames)) {
            $relNames = [$relNames];
        }
        if (!is_array($relTypes)) {
            $relTypes = [$relTypes];
        }
        $related = [];
        foreach ($relNames as $i => $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $type    = trim((string) ($relTypes[$i] ?? 'other')) ?: 'other';
                $related[] = ['name' => $name, 'type' => $type];
            }
        }
        $result['related'] = $related;

        if ($photoUpload !== null) {
            $result['photo_upload'] = $photoUpload;
        }

        if (($post['remove_photo'] ?? '') === '1') {
            $result['remove_photo'] = true;
        }

        return $result;
    }

    private function extractPhotoUpload(): ?array
    {
        $file = $_FILES['photo_file'] ?? null;
        if (!$file) {
            return null;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Photo upload failed. Please try again.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '') {
            throw new \RuntimeException('Invalid uploaded photo.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new \RuntimeException('Uploaded photo is empty.');
        }
        if ($size > (5 * 1024 * 1024)) {
            throw new \RuntimeException('Photo is too large. Maximum size is 5MB.');
        }

        $allowedMimes = [
            'image/jpeg'   => 'JPEG',
            'image/pjpeg'  => 'JPEG',
            'image/png'    => 'PNG',
            'image/gif'    => 'GIF',
            'image/webp'   => 'WEBP',
            'image/bmp'    => 'BMP',
            'image/x-ms-bmp' => 'BMP',
        ];

        $mimeType = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $tmpName);
                if (is_string($detected)) {
                    $mimeType = strtolower(trim($detected));
                }
                finfo_close($finfo);
            }
        }

        if ($mimeType === '' && function_exists('getimagesize')) {
            $imageMeta = @getimagesize($tmpName);
            if (is_array($imageMeta) && !empty($imageMeta['mime'])) {
                $mimeType = strtolower(trim((string) $imageMeta['mime']));
            }
        }

        if (!isset($allowedMimes[$mimeType])) {
            throw new \RuntimeException('Unsupported photo format. Allowed types: JPG, PNG, GIF, WEBP, BMP.');
        }

        $binary = (string) file_get_contents($tmpName);
        if ($binary === '') {
            throw new \RuntimeException('Failed to read uploaded photo content.');
        }

        return [
            'data'       => $binary,
            'mime'       => $mimeType,
            'vcard_type' => $allowedMimes[$mimeType],
        ];
    }

    private function importCsvContacts(string $username, string $csvContent): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csvContent);
        rewind($stream);

        $headers = fgetcsv($stream);
        if (!$headers) {
            throw new \RuntimeException('CSV file must include a header row.');
        }

        $headers = array_map(fn($h) => strtolower(trim((string) $h)), $headers);
        $format  = $this->detectCsvFormat($headers);

        $imported = 0;
        $failed = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($stream)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $index => $name) {
                if ($name === '') {
                    continue;
                }
                $assoc[$name] = trim((string) ($row[$index] ?? ''));
            }

            try {
                $data = match ($format) {
                    'google'  => $this->mapGoogleCsvRowToContact($assoc),
                    'outlook' => $this->mapOutlookCsvRowToContact($assoc),
                    default   => $this->mapCsvRowToContact($assoc),
                };
                Contact::create($username, $data);
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'CSV row ' . $rowNumber . ': ' . $e->getMessage();
            }
        }

        fclose($stream);

        return [
            'imported' => $imported,
            'failed'   => $failed,
            'errors'   => $errors,
        ];
    }

    /**
     * Detect whether a CSV came from Google Contacts, Outlook, or Cardy/generic.
     * @param string[] $headers lowercase trimmed column names
     */
    private function detectCsvFormat(array $headers): string
    {
        $set = array_flip($headers);

        // Google Contacts CSV uses "given name" / "family name" / "e-mail 1 - value"
        if (isset($set['given name']) || isset($set['family name']) || isset($set['e-mail 1 - value'])) {
            return 'google';
        }

        // Outlook CSV uses "e-mail address" (singular) together with Outlook-specific columns
        if (isset($set['e-mail address']) && (isset($set['business phone']) || isset($set['business street']) || isset($set['mobile phone']))) {
            return 'outlook';
        }

        return 'cardy';
    }

    private function mapGoogleCsvRowToContact(array $row): array
    {
        $firstName = $row['given name']   ?? '';
        $lastName  = $row['family name']  ?? '';

        if ($firstName === '' && $lastName === '') {
            $fn    = $row['name'] ?? '';
            $parts = preg_split('/\s+/', trim($fn), 2);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[1] ?? '';
        }

        $emails = [];
        for ($i = 1; $i <= 5; $i++) {
            $val  = $row['e-mail ' . $i . ' - value'] ?? '';
            $lbl  = strtolower($row['e-mail ' . $i . ' - label'] ?? '');
            if ($val !== '') {
                $type = str_contains($lbl, 'work') ? 'work' : (str_contains($lbl, 'home') ? 'home' : 'internet');
                $emails[] = ['address' => $val, 'type' => $type];
            }
        }

        $phones = [];
        for ($i = 1; $i <= 5; $i++) {
            $val = $row['phone ' . $i . ' - value'] ?? '';
            $lbl = strtolower($row['phone ' . $i . ' - label'] ?? '');
            if ($val !== '') {
                $type = str_contains($lbl, 'mobile') || str_contains($lbl, 'cell') ? 'cell'
                    : (str_contains($lbl, 'work') ? 'work'
                    : (str_contains($lbl, 'home') ? 'home' : 'voice'));
                $phones[] = ['number' => $val, 'type' => $type];
            }
        }

        $addresses = [];
        for ($i = 1; $i <= 3; $i++) {
            $street   = $row['address ' . $i . ' - street']      ?? '';
            $city     = $row['address ' . $i . ' - city']        ?? '';
            $region   = $row['address ' . $i . ' - region']      ?? '';
            $postcode = $row['address ' . $i . ' - postal code'] ?? '';
            $country  = $row['address ' . $i . ' - country']     ?? '';
            $lbl      = strtolower($row['address ' . $i . ' - label'] ?? '');
            if ($street !== '' || $city !== '') {
                $addresses[] = [
                    'type'     => str_contains($lbl, 'work') ? 'work' : 'home',
                    'street'   => $street,
                    'city'     => $city,
                    'region'   => $region,
                    'postcode' => $postcode,
                    'country'  => $country,
                ];
            }
        }

        $org   = $row['organization 1 - name']  ?? $row['organization name']  ?? '';
        $title = $row['organization 1 - title'] ?? $row['organization title'] ?? '';

        $birthday = $row['birthday'] ?? '';
        // Google sometimes uses --MM-DD format; normalize to YYYY-MM-DD
        if (preg_match('/^--(\d{2})-(\d{2})$/', $birthday, $m)) {
            $birthday = '0000-' . $m[1] . '-' . $m[2];
        }

        $urls = [];
        for ($i = 1; $i <= 3; $i++) {
            $val = $row['website ' . $i . ' - value'] ?? '';
            $lbl = strtolower($row['website ' . $i . ' - label'] ?? '');
            if ($val !== '') {
                $urls[] = ['value' => $val, 'type' => str_contains($lbl, 'work') ? 'work' : ''];
            }
        }

        if ($firstName === '' && $lastName === '' && empty($emails) && empty($phones) && $org === '') {
            throw new \RuntimeException('Contact row has no usable fields.');
        }

        return [
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'org'            => $org,
            'title'          => $title,
            'nickname'       => $row['nickname'] ?? '',
            'birthday'       => $birthday,
            'note'           => $row['notes'] ?? $row['note'] ?? '',
            'emails'         => $emails,
            'phones'         => $phones,
            'addresses'      => $addresses,
            'urls'           => $urls,
            'social_profiles' => [],
            'anniversaries'  => [],
            'custom_fields'  => [],
            'uid'            => '',
        ];
    }

    private function mapOutlookCsvRowToContact(array $row): array
    {
        $firstName = $row['first name'] ?? '';
        $lastName  = $row['last name']  ?? '';

        $emails = [];
        foreach (['e-mail address', 'e-mail 2 address', 'e-mail 3 address'] as $key) {
            $val = $row[$key] ?? '';
            if ($val !== '') {
                $emails[] = ['address' => $val, 'type' => 'internet'];
            }
        }

        $phones = [];
        $phoneMap = [
            'mobile phone'   => 'cell',
            'business phone' => 'work',
            'business phone 2' => 'work',
            'home phone'     => 'home',
            'home phone 2'   => 'home',
            'fax'            => 'fax',
            'other phone'    => 'other',
        ];
        foreach ($phoneMap as $key => $type) {
            $val = $row[$key] ?? '';
            if ($val !== '') {
                $phones[] = ['number' => $val, 'type' => $type];
            }
        }

        $addresses = [];
        if (($row['business street'] ?? '') !== '' || ($row['business city'] ?? '') !== '') {
            $addresses[] = [
                'type'     => 'work',
                'street'   => $row['business street'] ?? '',
                'city'     => $row['business city'] ?? '',
                'region'   => $row['business state'] ?? '',
                'postcode' => $row['business postal code'] ?? '',
                'country'  => $row['business country/region'] ?? $row['business country'] ?? '',
            ];
        }
        if (($row['home street'] ?? '') !== '' || ($row['home city'] ?? '') !== '') {
            $addresses[] = [
                'type'     => 'home',
                'street'   => $row['home street'] ?? '',
                'city'     => $row['home city'] ?? '',
                'region'   => $row['home state'] ?? '',
                'postcode' => $row['home postal code'] ?? '',
                'country'  => $row['home country/region'] ?? $row['home country'] ?? '',
            ];
        }

        $url  = $row['web page'] ?? '';
        $urls = $url !== '' ? [['value' => $url, 'type' => '']] : [];

        if ($firstName === '' && $lastName === '' && empty($emails) && empty($phones) && ($row['company'] ?? '') === '') {
            throw new \RuntimeException('Contact row has no usable fields.');
        }

        return [
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'org'            => $row['company']  ?? '',
            'title'          => $row['job title'] ?? '',
            'nickname'       => $row['nickname']  ?? '',
            'birthday'       => $row['birthday']  ?? '',
            'note'           => $row['notes']     ?? '',
            'emails'         => $emails,
            'phones'         => $phones,
            'addresses'      => $addresses,
            'urls'           => $urls,
            'social_profiles' => [],
            'anniversaries'  => [],
            'custom_fields'  => [],
            'uid'            => '',
        ];
    }

    private function mapCsvRowToContact(array $row): array
    {
        $firstName = $row['first_name'] ?? $row['given_name'] ?? '';
        $lastName = $row['last_name'] ?? $row['family_name'] ?? '';
        $fn = $row['fn'] ?? $row['full_name'] ?? '';

        if ($firstName === '' && $lastName === '' && $fn !== '') {
            $parts = preg_split('/\s+/', trim($fn), 2);
            $firstName = $parts[0] ?? '';
            $lastName = $parts[1] ?? '';
        }

        $emails = [];
        for ($i = 1; $i <= 3; $i++) {
            $email = $row['email' . $i] ?? ($i === 1 ? ($row['email'] ?? '') : '');
            if ($email !== '') {
                $emails[] = [
                    'address' => $email,
                    'type' => $row['email' . $i . '_type'] ?? 'internet',
                ];
            }
        }

        $phones = [];
        for ($i = 1; $i <= 3; $i++) {
            $phone = $row['phone' . $i] ?? ($i === 1 ? ($row['phone'] ?? '') : '');
            if ($phone !== '') {
                $phones[] = [
                    'number' => $phone,
                    'type' => $row['phone' . $i . '_type'] ?? 'voice',
                ];
            }
        }

        $addresses = [];
        $home = [
            'street'   => $row['home_street'] ?? '',
            'city'     => $row['home_city'] ?? '',
            'region'   => $row['home_region'] ?? '',
            'postcode' => $row['home_postcode'] ?? '',
            'country'  => $row['home_country'] ?? '',
        ];
        if (implode('', $home) !== '') {
            $home['type'] = 'home';
            $addresses[] = $home;
        }

        $work = [
            'street'   => $row['work_street'] ?? '',
            'city'     => $row['work_city'] ?? '',
            'region'   => $row['work_region'] ?? '',
            'postcode' => $row['work_postcode'] ?? '',
            'country'  => $row['work_country'] ?? '',
        ];
        if (implode('', $work) !== '') {
            $work['type'] = 'work';
            $addresses[] = $work;
        }

        if ($firstName === '' && $lastName === '' && empty($emails) && empty($phones) && ($row['org'] ?? '') === '') {
            throw new \RuntimeException('Contact row has no usable fields.');
        }

        // URLs
        $urls = [];
        for ($i = 1; $i <= 2; $i++) {
            $val = $row['url' . $i] ?? '';
            if ($val !== '') {
                $urls[] = [
                    'value' => $val,
                    'type'  => $row['url' . $i . '_type'] ?? '',
                ];
            }
        }

        // Social profiles
        $socialProfiles = [];
        for ($i = 1; $i <= 3; $i++) {
            $val  = $row['social' . $i . '_value'] ?? '';
            $type = $row['social' . $i . '_type']  ?? 'other';
            if ($val !== '') {
                $socialProfiles[] = ['type' => $type ?: 'other', 'value' => $val];
            }
        }

        // Anniversaries
        $anniversaries = [];
        for ($i = 1; $i <= 2; $i++) {
            $val = $row['anniversary' . $i] ?? '';
            if ($val !== '') {
                $anniversaries[] = $val;
            }
        }

        // Custom fields
        $customFields = [];
        for ($i = 1; $i <= 2; $i++) {
            $val   = $row['custom' . $i . '_value'] ?? '';
            $label = $row['custom' . $i . '_label'] ?? 'Custom';
            if ($val !== '') {
                $customFields[] = ['label' => $label ?: 'Custom', 'value' => $val];
            }
        }

        return [
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'org'            => $row['org']      ?? '',
            'title'          => $row['title']    ?? '',
            'nickname'       => $row['nickname'] ?? '',
            'birthday'       => $row['birthday'] ?? '',
            'note'           => $row['note']     ?? '',
            'emails'         => $emails,
            'phones'         => $phones,
            'addresses'      => $addresses,
            'urls'           => $urls,
            'social_profiles' => $socialProfiles,
            'anniversaries'  => $anniversaries,
            'custom_fields'  => $customFields,
            'uid'            => '',
        ];
    }

    // -------------------------------------------------------
    // Starred
    // -------------------------------------------------------

    public function toggleStar(array $params): void
    {
        $user       = $this->requireAuth();
        $this->verifyCsrf();
        $id         = (int) ($params['id'] ?? 0);
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        $newState   = Contact::toggleStar($id, $user['username'], $activeAbId);
        // AJAX response
        if ($this->isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['starred' => $newState]);
            exit;
        }
        $this->redirect('/contacts/' . $id);
    }

    // -------------------------------------------------------
    // Bulk actions
    // -------------------------------------------------------

    public function bulkAction(): void
    {
        $user       = $this->requireAuth();
        $this->verifyCsrf();
        $activeAbId = $this->getActiveAddressBookId($user['username']);

        $ids    = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
        $action = trim((string) ($_POST['action'] ?? ''));

        if (empty($ids)) {
            $this->flash('error', 'No contacts selected.');
            $this->redirect('/contacts');
            return;
        }

        switch ($action) {
            case 'delete':
                $count = Contact::bulkDelete($ids, $user['username'], $activeAbId);
                $this->flash('success', "Deleted {$count} contact(s).");
                break;

            case 'star':
                Contact::bulkStar($ids, $user['username'], true, $activeAbId);
                $this->flash('success', 'Starred ' . count($ids) . ' contact(s).');
                break;

            case 'unstar':
                Contact::bulkStar($ids, $user['username'], false, $activeAbId);
                $this->flash('success', 'Unstarred ' . count($ids) . ' contact(s).');
                break;

            case 'add_group':
                $groupId = (int) ($_POST['group_id'] ?? 0);
                if ($groupId > 0) {
                    Contact::bulkAddGroup($ids, $user['username'], $groupId, $activeAbId);
                    $this->flash('success', 'Added ' . count($ids) . ' contact(s) to group.');
                } else {
                    $this->flash('error', 'Please select a group.');
                }
                break;

            default:
                $this->flash('error', 'Unknown action.');
        }

        $this->redirect('/contacts');
    }

    // -------------------------------------------------------
    // Groups
    // -------------------------------------------------------

    public function groups(): void
    {
        $user      = $this->requireAuth();
        $allGroups = Contact::getAllGroups($user['username']);

        $this->render('contacts/groups', [
            'user'      => $user,
            'groups'    => $allGroups,
            'csrf'      => $this->csrfToken(),
            'flash'     => $this->getFlash(),
        ]);
    }

    public function createGroup(): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $name  = trim((string) ($_POST['name'] ?? ''));
        $color = trim((string) ($_POST['color'] ?? ''));

        try {
            Contact::createGroup($user['username'], $name, $color);
            $this->flash('success', "Group \"{$name}\" created.");
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('/contacts/groups');
    }

    public function updateGroup(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $groupId = (int) ($params['id'] ?? 0);
        $name    = trim((string) ($_POST['name'] ?? ''));
        $color   = trim((string) ($_POST['color'] ?? ''));

        try {
            Contact::updateGroup($groupId, $user['username'], $name, $color);
            $this->flash('success', "Group updated.");
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('/contacts/groups');
    }

    public function deleteGroup(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $groupId = (int) ($params['id'] ?? 0);
        Contact::deleteGroup($groupId, $user['username']);
        $this->flash('success', 'Group deleted.');
        $this->redirect('/contacts/groups');
    }

    // -------------------------------------------------------
    // Duplicate detection
    // -------------------------------------------------------

    public function duplicates(): void
    {
        $user       = $this->requireAuth();
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        $duplicates = Contact::findDuplicates($user['username'], $activeAbId);

        $this->render('contacts/duplicates', [
            'user'       => $user,
            'duplicates' => $duplicates,
            'csrf'       => $this->csrfToken(),
            'flash'      => $this->getFlash(),
        ]);
    }

    public function toggleIgnoreDuplicate(array $params): void
    {
        $user       = $this->requireAuth();
        $this->verifyCsrf();
        $id         = (int) ($params['id'] ?? 0);
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        Contact::toggleIgnoreDuplicate($id, $user['username'], $activeAbId);
        $this->flash('success', 'Duplicate flag updated.');
        $this->redirect('/contacts/duplicates');
    }

    // -------------------------------------------------------
    // Merge
    // -------------------------------------------------------

    public function mergeForm(array $params): void
    {
        $user       = $this->requireAuth();
        $activeAbId = $this->getActiveAddressBookId($user['username']);
        $keepId     = (int) ($params['id'] ?? 0);
        $otherId    = (int) ($_GET['other'] ?? 0);

        $keep  = Contact::findById($keepId,  $user['username'], $activeAbId);
        $other = Contact::findById($otherId, $user['username'], $activeAbId);

        if (!$keep || !$other) {
            $this->abort(404, 'One or both contacts not found.');
        }

        $this->render('contacts/merge', [
            'user'  => $user,
            'keep'  => $keep,
            'other' => $other,
            'csrf'  => $this->csrfToken(),
        ]);
    }

    public function mergeSubmit(array $params): void
    {
        $user    = $this->requireAuth();
        $this->verifyCsrf();
        $activeAbId = $this->getActiveAddressBookId($user['username']);

        $keepId    = (int) ($params['id'] ?? 0);
        $discardId = (int) ($_POST['discard_id'] ?? 0);

        if ($discardId === 0) {
            $this->flash('error', 'Invalid merge request.');
            $this->redirect('/contacts/duplicates');
            return;
        }

        $data = $this->extractFormData();

        try {
            $survivingId = Contact::mergeContacts($keepId, $discardId, $user['username'], $data, $activeAbId);
            $this->flash('success', 'Contacts merged successfully.');
            $this->redirect('/contacts/' . $survivingId);
        } catch (\Exception $e) {
            $this->flash('error', 'Merge failed: ' . $e->getMessage());
            $this->redirect('/contacts/duplicates');
        }
    }

    // -------------------------------------------------------
    // Address-book management endpoints
    // -------------------------------------------------------

    public function switchAddressBook(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();
        $id   = (int) $params['id'];
        $books = Contact::getAllAddressBooksForUser($user['username']);
        foreach ($books as $book) {
            if ((int) $book['id'] === $id) {
                $this->setActiveAddressBookId($user['username'], $id);
                break;
            }
        }
        $this->redirect('/contacts');
    }

    public function createAddressBookAction(): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();
        $displayName = trim($_POST['displayname'] ?? '');
        if ($displayName === '') {
            $this->flash('error', 'Address book name is required.');
            $this->redirect('/contacts');
            return;
        }
        try {
            $book = Contact::createAddressBook($user['username'], $displayName);
            $this->setActiveAddressBookId($user['username'], (int) $book['id']);
            $this->flash('success', 'Address book "' . htmlspecialchars($book['displayname']) . '" created. DAV URL: .../addressbooks/' . htmlspecialchars($user['username']) . '/' . htmlspecialchars($book['uri']) . '/');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to create address book: ' . $e->getMessage());
        }
        $this->redirect('/contacts');
    }

    public function renameAddressBookAction(array $params): void
    {
        $user        = $this->requireAuth();
        $this->verifyCsrf();
        $id          = (int) $params['id'];
        $displayName = trim($_POST['displayname'] ?? '');
        if ($displayName === '') {
            $this->flash('error', 'Name cannot be empty.');
            $this->redirect('/contacts');
            return;
        }
        try {
            Contact::renameAddressBook($id, $user['username'], $displayName);
            $this->flash('success', 'Address book renamed.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to rename: ' . $e->getMessage());
        }
        $this->redirect('/contacts');
    }

    public function deleteAddressBookAction(array $params): void
    {
        $user     = $this->requireAuth();
        $this->verifyCsrf();
        $id       = (int) $params['id'];
        $activeId = $this->getActiveAddressBookId($user['username']);
        try {
            Contact::deleteAddressBook($id, $user['username']);
            if ($activeId === $id) {
                unset($_SESSION['active_ab_' . $user['username']]);
            }
            $this->flash('success', 'Address book deleted.');
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/contacts');
    }

    // -------------------------------------------------------
    // Utility
    // -------------------------------------------------------

    private function isJsonRequest(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }
}
