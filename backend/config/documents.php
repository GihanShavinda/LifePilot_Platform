<?php
return ['disk'=>env('DOCUMENTS_DISK','documents'),'max_upload_kb'=>(int)env('DOCUMENT_MAX_UPLOAD_KB',15360)];
