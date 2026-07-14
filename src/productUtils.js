import { demandLabels, filterOptions } from './catalogConfig.js';

export const text = (value, lang) => {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string') return value;
  return value?.[lang] ?? '';
};

export const discount = (product) => {
  if (!product.oldPrice || product.oldPrice <= product.price) return 0;
  return Math.round((1 - product.price / product.oldPrice) * 100);
};

const synonymGroups = [
  ['dell', 'den'],
  ['lenovo', 'thinkpad', 'think pad'],
  ['hp', 'hewlett packard'],
  ['apple', 'macbook', 'mac'],
  ['asus'],
  ['acer'],
  ['microsoft', 'surface'],
  ['laptop', 'notebook', 'may tinh xach tay', 'may tinh'],
  ['old', 'used', 'second hand', 'cu', 'may cu', 'laptop cu'],
  ['new', 'moi', 'hang moi'],
  ['office', 'van phong', 'hoc tap', 'student', 'sinh vien'],
  ['gaming', 'game', 'choi game'],
  ['creator', 'do hoa', 'thiet ke', 'sang tao noi dung'],
  ['render', 'dung phim', 'edit video'],
  ['coding', 'lap trinh', 'code', 'developer'],
  ['thin light', 'mong nhe', 'mỏng nhẹ'],
  ['workstation', 'tram do hoa', 'trạm đồ họa'],
  ['graphics', 'gpu', 'vga', 'card roi', 'card do hoa', 'card man hinh', 'do hoa'],
  ['onboard', 'gpu onboard', 'card onboard', 'tich hop'],
  ['touch', 'cam ung', 'cảm ứng'],
  ['warranty', 'bao hanh', 'bảo hành'],
];

const normalizeSeparators = (value) => String(value || '')
  .replace(/([a-z])([0-9])/gi, '$1 $2')
  .replace(/([0-9])([a-z])/gi, '$1 $2')
  .replace(/([a-z])(gb|tb)\b/gi, '$1 $2');

export const normalizeSearchText = (value) => normalizeSeparators(value)
  .toLowerCase()
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/đ/g, 'd')
  .replace(/[\-–—_+\/\\|,;:()[\]{}'"`~!@#$%^&*=<>?]+/g, ' ')
  .replace(/\s+/g, ' ')
  .trim();

const compactText = (value) => normalizeSearchText(value).replace(/\s+/g, '');

const expandSynonyms = (value) => {
  const normalized = normalizeSearchText(value);
  if (!normalized) return '';
  const extra = [];
  synonymGroups.forEach((group) => {
    if (group.some((term) => normalized.includes(normalizeSearchText(term)))) extra.push(...group);
  });
  return normalizeSearchText([normalized, ...extra].join(' '));
};

const cpuAliases = {
  i3: ['i3', 'core i3'],
  i5: ['i5', 'core i5'],
  i7: ['i7', 'core i7'],
  i9: ['i9', 'core i9'],
  'Core Ultra': ['core ultra', 'ultra 5', 'ultra 7', 'ultra 9'],
  'Apple M': ['apple m', 'm1', 'm2', 'm3', 'm4'],
};

export const matchesCpuFamily = (cpu, selected) => {
  if (selected === 'all') return true;
  const value = normalizeSearchText(cpu);
  const selectedValue = normalizeSearchText(selected);
  if (selected === 'Other') return !filterOptions.cpu.slice(1, -1).some((item) => matchesCpuFamily(cpu, item));
  const aliases = cpuAliases[selected] || [selectedValue];
  return aliases.some((alias) => value.includes(normalizeSearchText(alias)) || compactText(cpu).includes(compactText(alias)));
};

export const hasGpuValue = (gpu) => Boolean(gpu && !['n/a', 'lien he', 'dang cap nhat'].includes(normalizeSearchText(gpu)));

export const isDiscreteGpu = (gpu) => /(rtx|gtx|quadro|nvidia|geforce|\bmx\d|radeon\s+pro|arc\s+a\d|\bpro\s+w\d|\bt\d{3,4}\b|\bp\d{3,4}\b|vga\s*\d)/i.test(normalizeSearchText(gpu));

const gpuMatchPatterns = {
  'gpu-roi': isDiscreteGpu,
  RTX: (gpu) => /\brtx\b/i.test(normalizeSearchText(gpu)),
  workstation: (gpu) => /quadro|rtx\s*a\d{3,4}|\bt\d{3,4}\b|\bm\d{3,4}\b|\bp\d{3,4}\b/i.test(normalizeSearchText(gpu)),
  'GTX/MX': (gpu) => /\bgtx\b|\bmx\s*\d+/i.test(normalizeSearchText(gpu)),
  Radeon: (gpu) => /radeon|\bpro\s+w\d|vega\s+m\s+gl/i.test(normalizeSearchText(gpu)),
  'Intel Arc': (gpu) => /\barc\s*a\d|intel\s+arc/i.test(normalizeSearchText(gpu)),
  onboard: (gpu) => hasGpuValue(gpu) && !isDiscreteGpu(gpu),
};

export const matchesGpuFamily = (gpu, selected) => {
  if (selected === 'all') return true;
  const value = gpu || '';
  if (selected === 'Other') return hasGpuValue(value) && !Object.entries(gpuMatchPatterns).some(([key, matcher]) => key !== 'gpu-roi' && matcher(value));
  return (gpuMatchPatterns[selected] || ((item) => normalizeSearchText(item).includes(normalizeSearchText(selected))))(value);
};

const screenSizeNumber = (screen) => {
  const match = String(screen || '').replace(',', '.').match(/\d+(?:\.\d+)?/);
  return match ? Number(match[0]) : null;
};

export const matchesScreenSize = (screen, selected) => {
  if (selected === 'all') return true;
  if (selected === 'Other') return !filterOptions.screen.slice(1, -1).some((item) => matchesScreenSize(screen, item));
  const size = screenSizeNumber(screen);
  return size !== null && Math.floor(size) === Number(selected);
};

export const matchesDemand = (demand, selected) => {
  if (selected === 'all') return true;
  if (selected === 'other') return !filterOptions.demand.slice(1, -1).includes(demand);
  return normalizeSearchText(demand) === normalizeSearchText(selected);
};

const demandSearchLabels = (demand) => [
  demand,
  demandLabels[demand],
  demand === 'office' ? 'van phong office hoc tap student ke toan ban hang' : '',
  demand === 'student' ? 'hoc sinh sinh vien hoc tap student school' : '',
  demand === 'gaming' ? 'gaming game choi game gpu roi' : '',
  demand === 'creator' ? 'do hoa thiet ke photoshop ai creator sang tao noi dung' : '',
  demand === 'render' ? 'render dung phim edit video premiere' : '',
  demand === 'coding' ? 'lap trinh coding code developer dev' : '',
].filter(Boolean);

export const searchableText = (product, lang) => expandSynonyms([
  product.name,
  product.brand,
  product.category,
  product.type,
  product.cpu,
  product.gpu,
  product.ram,
  product.ssd,
  product.screen,
  ...demandSearchLabels(product.demand),
  text(product.condition, lang),
  text(product.badge, lang),
  text(product.promo, lang),
  ...text(product.specs, lang),
  ...(product.variants || []).flatMap((variant) => [variant.label, variant.cpu, variant.gpu, variant.ram, variant.ssd, variant.screen]),
  isDiscreteGpu(product.gpu) ? 'gpu card vga roi do hoa graphics card card man hinh roi dedicated discrete' : '',
].filter(Boolean).join(' '));

export const matchesSearchQuery = (product, lang, query) => {
  const normalizedQuery = expandSynonyms(query);
  if (!normalizedQuery) return true;
  const searchable = searchableText(product, lang);
  const searchableCompact = compactText(searchable);
  return normalizedQuery.split(/\s+/).every((token) => searchable.includes(token) || searchableCompact.includes(compactText(token)));
};
