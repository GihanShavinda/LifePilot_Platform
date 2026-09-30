export type DocumentRecord = {
  id: number;
  title: string;
  category: string | null;
  source: string | null;
  original_filename: string;
  mime_type: string;
  size: number;
  checksum: string;
  document_date: string | null;
  issuer: string | null;
  status: 'active' | 'archived';
  processing_status:
    | 'pending'
    | 'queued'
    | 'processing'
    | 'ready'
    | 'failed'
    | 'rejected';
  intelligence_status:
    | 'queued'
    | 'processing'
    | 'needs_review'
    | 'completed'
    | 'failed'
    | null;
  intelligence_version: number | null;
  tags: string[];
  created_at: string;
  updated_at: string;
  versions?: Array<{
    id: number;
    version_number: number;
    original_filename: string;
    mime_type: string;
    size: number;
    checksum: string;
    created_at: string;
  }>;
};

export type DocumentFilters = {
  search?: string;
  category?: string;
  issuer?: string;
  status?: string;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
};
