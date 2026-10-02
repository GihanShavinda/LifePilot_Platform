export type SearchSource = {
  type: string;
  document_id?: number;
  extraction_id?: number;
  chunk_index?: number;
  id?: number | string;
};
export type SearchResult = {
  kind: string;
  id: number;
  score: number;
  title: string;
  snippet?: string;
  metadata: Record<string, unknown>;
  sources: SearchSource[];
};
export type LifeEntity = {
  id: number;
  entity_type: string;
  label: string;
  search_text?: string;
  metadata?: Record<string, unknown>;
  source_reference?: SearchSource;
};
