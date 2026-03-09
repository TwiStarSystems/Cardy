<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\Contact;
use Cardy\WebUI\Controller;

class ContactsController extends Controller
{
    public function index(): void
    {
        $user     = $this->requireAuth();
        $search   = trim($_GET['q'] ?? '');
        $contacts = Contact::allForUser($user['username'], $search);

        $this->render('contacts/index', [
            'user'     => $user,
            'contacts' => $contacts,
            'search'   => $search,
            'csrf'     => $this->csrfToken(),
            'flash'    => $this->getFlash(),
        ]);
    }

    public function view(array $params): void
    {
        $user    = $this->requireAuth();
        $contact = Contact::findById((int) $params['id'], $user['username']);
        if (!$contact) {
            $this->abort(404, 'Contact not found.');
        }

        $this->render('contacts/view', [
            'user'    => $user,
            'contact' => $contact,
            'csrf'    => $this->csrfToken(),
        ]);
    }

    public function create(): void
    {
        $user = $this->requireAuth();
        $this->render('contacts/form', [
            'user'    => $user,
            'contact' => null,
            'csrf'    => $this->csrfToken(),
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

        try {
            if ($isVCard) {
                $result = Contact::importVCardData($user['username'], $content);
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
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $data = $this->extractFormData();

        try {
            Contact::create($user['username'], $data);
            $this->flash('success', 'Contact created successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to create contact: ' . $e->getMessage());
        }

        $this->redirect('/contacts');
    }

    public function edit(array $params): void
    {
        $user    = $this->requireAuth();
        $contact = Contact::findById((int) $params['id'], $user['username']);
        if (!$contact) {
            $this->abort(404, 'Contact not found.');
        }

        $this->render('contacts/form', [
            'user'    => $user,
            'contact' => $contact,
            'csrf'    => $this->csrfToken(),
        ]);
    }

    public function update(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $contact = Contact::findById((int) $params['id'], $user['username']);
        if (!$contact) {
            $this->abort(404, 'Contact not found.');
        }

        $data = $this->extractFormData();
        $data['uid'] = $contact['uid'];

        try {
            Contact::update((int) $params['id'], $user['username'], $data);
            $this->flash('success', 'Contact updated successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to update contact: ' . $e->getMessage());
        }

        $this->redirect('/contacts/' . $params['id']);
    }

    public function delete(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        Contact::delete((int) $params['id'], $user['username']);
        $this->flash('success', 'Contact deleted.');
        $this->redirect('/contacts');
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function extractFormData(): array
    {
        $post = $_POST;

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

        return [
            'first_name'  => $post['first_name']  ?? '',
            'last_name'   => $post['last_name']   ?? '',
            'org'         => $post['org']         ?? '',
            'title'       => $post['title']       ?? '',
            'birthday'    => $post['birthday']    ?? '',
            'note'        => $post['note']        ?? '',
            'emails'      => $emails,
            'phones'      => $phones,
            'addresses'   => $addresses,
            'uid'         => $post['uid']         ?? '',
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
                $data = $this->mapCsvRowToContact($assoc);
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

        return [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'org'        => $row['org'] ?? '',
            'title'      => $row['title'] ?? '',
            'birthday'   => $row['birthday'] ?? '',
            'note'       => $row['note'] ?? '',
            'emails'     => $emails,
            'phones'     => $phones,
            'addresses'  => $addresses,
            'uid'        => '',
        ];
    }
}
