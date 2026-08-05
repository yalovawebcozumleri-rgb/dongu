export type LegalDocumentKey = 'terms' | 'privacy';

export type LegalDocument = {
  key: LegalDocumentKey;
  title: string;
  short_title: string;
  version: string;
  effective_date: string;
  summary: string;
  sections: Array<{ title: string; paragraphs: string[] }>;
  operator: { name: string; address?: string | null; email: string };
};

export const TERMS_VERSION = '2026-08-05.2';
export const PRIVACY_NOTICE_VERSION = '2026-08-05.2';
