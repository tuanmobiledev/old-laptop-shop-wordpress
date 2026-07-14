import React from 'react';
import { ShieldCheck } from 'lucide-react';
import { contacts } from './data.js';

export default function SalesPolicyPage({ initialSection = '', t } = {}) {
  const warrantyItems = t.policyWarrantyItems;
  const returnItems = t.policyReturnItems;
  const exclusions = t.policyExclusions;
  const process = t.policyProcess;
  return (
    <section className="section shell sales-policy-page" id="policy">
      <div className="policy-hero">
        <span className="eyebrow"><ShieldCheck size={16} /> {t.policyEyebrow}</span>
        <h1>{t.policyHeroTitle}</h1>
        <p>{t.policyHeroDesc}</p>
        <div>
          <span className="primary phone-display">{t.policyHotline}: {contacts.hotline}</span>
          <a className="secondary dark" href="#products">{t.aboutCtaProducts}</a>
        </div>
      </div>
      <div className="policy-layout">
        <aside className="policy-toc">
          <strong>{t.policyMain}</strong>
          <a href="#policy-warranty">{t.warrantyNav}</a>
          <a href="#policy-return">{t.returnsNav}</a>
          <a href="#policy-exclusion">{t.exclusionNav}</a>
          <a href="#policy-delivery">{t.deliveryNav}</a>
          <a href="#policy-data">{t.dataNav}</a>
        </aside>
        <div className="policy-content">
          <section id="policy-warranty">
            <h2>{t.policyWarrantyTitle}</h2>
            <div className="policy-cards">
              {warrantyItems.map(([title, desc]) => (
                <article key={title}><h3>{title}</h3><p>{desc}</p></article>
              ))}
            </div>
            <p className="policy-note">{t.policyWarrantyNote}</p>
          </section>
          <section id="policy-return">
            <h2>{t.policyReturnTitle}</h2>
            <ul className="policy-list">
              {returnItems.map((item) => <li key={item}>{item}</li>)}
            </ul>
            <p>{t.policyReturnNote}</p>
          </section>
          <section id="policy-exclusion">
            <h2>{t.policyExclusionTitle}</h2>
            <ul className="policy-list warning">
              {exclusions.map((item) => <li key={item}>{item}</li>)}
            </ul>
          </section>
          <section id="policy-delivery">
            <h2>{t.policyDeliveryTitle}</h2>
            <p>{t.policyDeliveryDesc}</p>
            <p>{t.policyDeliveryRemoteDesc}</p>
          </section>
          <section id="policy-data">
            <h2>{t.policyDataTitle}</h2>
            <p>{t.policyDataDesc}</p>
          </section>
          <section>
            <h2>6. {t.policyProcessTitle}</h2>
            <div className="policy-process">
              {process.map((step, index) => (
                <article key={step}><b>{index + 1}</b><span>{step}</span></article>
              ))}
            </div>
          </section>
          <section>
            <h2>{t.policyGeneralTitle}</h2>
            <p>{t.policyGeneralDesc}</p>
            <p>{t.policySpecialTerms}</p>
          </section>
        </div>
      </div>
    </section>
  );
}
