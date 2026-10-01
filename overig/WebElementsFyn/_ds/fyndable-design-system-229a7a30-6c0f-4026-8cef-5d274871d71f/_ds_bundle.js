/* @ds-bundle: {"format":4,"namespace":"FyndableDesignSystem_229a7a","components":[{"name":"Badge","sourcePath":"components/core/Badge.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"IconButton","sourcePath":"components/core/IconButton.jsx"},{"name":"Spinner","sourcePath":"components/core/Spinner.jsx"},{"name":"StatusPill","sourcePath":"components/core/StatusPill.jsx"},{"name":"Tooltip","sourcePath":"components/core/Tooltip.jsx"},{"name":"AnalysisList","sourcePath":"components/data/AnalysisList.jsx"},{"name":"DataTable","sourcePath":"components/data/DataTable.jsx"},{"name":"FeatureList","sourcePath":"components/data/FeatureList.jsx"},{"name":"Pagination","sourcePath":"components/data/Pagination.jsx"},{"name":"ProgressBar","sourcePath":"components/data/ProgressBar.jsx"},{"name":"ScoreRing","sourcePath":"components/data/ScoreRing.jsx"},{"name":"SerpPreview","sourcePath":"components/data/SerpPreview.jsx"},{"name":"StatCard","sourcePath":"components/data/StatCard.jsx"},{"name":"Alert","sourcePath":"components/feedback/Alert.jsx"},{"name":"Modal","sourcePath":"components/feedback/Modal.jsx"},{"name":"FormField","sourcePath":"components/forms/FormField.jsx"},{"name":"LicenseKeyField","sourcePath":"components/forms/LicenseKeyField.jsx"},{"name":"Card","sourcePath":"components/layout/Card.jsx"},{"name":"EmptyState","sourcePath":"components/layout/EmptyState.jsx"},{"name":"NavPills","sourcePath":"components/layout/NavPills.jsx"},{"name":"PageHeader","sourcePath":"components/layout/PageHeader.jsx"},{"name":"QuickAction","sourcePath":"components/layout/QuickAction.jsx"},{"name":"Tabs","sourcePath":"components/layout/Tabs.jsx"},{"name":"ToolCard","sourcePath":"components/layout/ToolCard.jsx"}],"sourceHashes":{"components/core/Badge.jsx":"4aed7de7e7cb","components/core/Button.jsx":"1539f99f1df0","components/core/IconButton.jsx":"ddacdac923ad","components/core/Spinner.jsx":"944d9841a763","components/core/StatusPill.jsx":"c5e83d9533df","components/core/Tooltip.jsx":"b8f85637414d","components/data/AnalysisList.jsx":"cd2a0b0d9f08","components/data/DataTable.jsx":"cf59aa1620c2","components/data/FeatureList.jsx":"04fb6db430a8","components/data/Pagination.jsx":"1544ae17491e","components/data/ProgressBar.jsx":"4af6b2bfa223","components/data/ScoreRing.jsx":"1deac86f9978","components/data/SerpPreview.jsx":"550c50bd514a","components/data/StatCard.jsx":"d769629688ed","components/feedback/Alert.jsx":"5de477e1b5f1","components/feedback/Modal.jsx":"80c7e4e72e94","components/forms/FormField.jsx":"69ccb77fcc78","components/forms/LicenseKeyField.jsx":"a6a29f442119","components/layout/Card.jsx":"2f00219442b9","components/layout/EmptyState.jsx":"124c4ea21191","components/layout/NavPills.jsx":"bb72838eed27","components/layout/PageHeader.jsx":"10f8b4be6933","components/layout/QuickAction.jsx":"6dcee300927c","components/layout/Tabs.jsx":"f7a7c46749e2","components/layout/ToolCard.jsx":"f636dedb91a9","ui_kits/saas_dashboard/LicensesScreen.jsx":"18e9a6b5ff65","ui_kits/saas_dashboard/OverviewScreen.jsx":"5cfa6f2e4a02","ui_kits/saas_dashboard/SaasChrome.jsx":"45eaa3c65b13","ui_kits/saas_dashboard/SaasDashboardApp.jsx":"e16956038f7e","ui_kits/saas_dashboard/TenantsScreen.jsx":"64417b68d0df","ui_kits/saas_dashboard/WhiteLabelScreen.jsx":"74dc6c10769f","ui_kits/wp_client/AiToolsScreen.jsx":"8f87e7fe47da","ui_kits/wp_client/ClientApp.jsx":"9cdd067b2917","ui_kits/wp_client/ConnectionScreen.jsx":"ddd67bc5f586","ui_kits/wp_client/KeywordsScreen.jsx":"737e628f0a35","ui_kits/wp_client/SearchConsoleScreen.jsx":"f057bc6f5f68","ui_kits/wp_client/StatisticsScreen.jsx":"7ba64d98da2b","ui_kits/wp_client/WpChrome.jsx":"561a9c93c2d0"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.FyndableDesignSystem_229a7a = window.FyndableDesignSystem_229a7a || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/core/Badge.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const tones = {
  success: {
    background: 'var(--fyn-success-light)',
    color: 'var(--fyn-success)'
  },
  warning: {
    background: 'var(--fyn-warning-light)',
    color: 'var(--fyn-warning)'
  },
  danger: {
    background: 'var(--fyn-danger-light)',
    color: 'var(--fyn-danger)'
  },
  info: {
    background: 'var(--fyn-info-light)',
    color: 'var(--fyn-info)'
  },
  accent: {
    background: 'var(--fyn-accent-light)',
    color: 'var(--fyn-accent)'
  },
  brand: {
    background: 'var(--fyn-purple-tint)',
    color: 'var(--color-primary)'
  },
  neutral: {
    background: 'var(--fyn-gray-100)',
    color: 'var(--fyn-gray-600)'
  }
};

/** Small pill label — keyword difficulty, intent, post status, license tier. */
function Badge({
  tone = 'neutral',
  uppercase = false,
  children,
  style,
  ...rest
}) {
  const css = Object.assign({
    display: 'inline-block',
    padding: '4px 10px',
    borderRadius: 'var(--radius-lg)',
    fontSize: 'var(--text-3xs)',
    fontWeight: 'var(--weight-semibold)',
    lineHeight: '18px',
    letterSpacing: uppercase ? 'var(--tracking-wide)' : 'normal',
    textTransform: uppercase ? 'uppercase' : 'none'
  }, tones[tone] || tones.neutral, style);
  return /*#__PURE__*/React.createElement("span", _extends({
    style: css
  }, rest), children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Badge.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const base = {
  display: 'inline-flex',
  alignItems: 'center',
  justifyContent: 'center',
  gap: '8px',
  fontFamily: 'var(--font-core)',
  fontWeight: 'var(--weight-semibold)',
  borderRadius: 'var(--radius-md)',
  border: 'none',
  cursor: 'pointer',
  transition: 'var(--transition)',
  textDecoration: 'none',
  lineHeight: 1.5,
  whiteSpace: 'nowrap'
};
const sizes = {
  sm: {
    padding: '6px 12px',
    fontSize: 'var(--text-2xs)'
  },
  md: {
    padding: '10px 20px',
    fontSize: 'var(--text-ui)'
  },
  lg: {
    padding: '14px 24px',
    fontSize: 'var(--text-sm)'
  },
  hero: {
    padding: '15px 30px',
    fontSize: 'var(--text-sm)'
  }
};
const variants = {
  primary: {
    background: 'var(--fyn-gradient)',
    color: 'var(--text-inverse)',
    boxShadow: '0 4px 6px -1px rgba(143,57,172,.3)'
  },
  secondary: {
    background: 'var(--fyn-gray-100)',
    color: 'var(--fyn-gray-700)'
  },
  outline: {
    background: 'var(--fyn-white)',
    color: 'var(--fyn-gray-700)',
    border: '2px solid var(--fyn-gray-200)'
  },
  success: {
    background: 'var(--fyn-success)',
    color: 'var(--text-inverse)'
  },
  danger: {
    background: 'var(--fyn-danger)',
    color: 'var(--text-inverse)'
  },
  ghost: {
    background: 'transparent',
    color: 'var(--color-primary)'
  }
};
const hovers = {
  primary: {
    transform: 'var(--lift-md)',
    boxShadow: 'var(--shadow-brand-strong)'
  },
  secondary: {
    background: 'var(--fyn-gray-200)'
  },
  outline: {
    background: 'var(--fyn-gray-100)',
    borderColor: 'var(--fyn-gray-300)'
  },
  success: {
    transform: 'var(--lift-sm)',
    boxShadow: 'var(--shadow-md)'
  },
  danger: {
    transform: 'var(--lift-sm)',
    boxShadow: 'var(--shadow-md)'
  },
  ghost: {
    background: 'var(--fyn-purple-tint)'
  }
};
function Button({
  variant = 'primary',
  size = 'md',
  disabled = false,
  icon,
  as = 'button',
  children,
  style,
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const Tag = as;
  const css = Object.assign({}, base, sizes[size] || sizes.md, variants[variant] || variants.primary, hover && !disabled ? hovers[variant] : null, disabled ? {
    opacity: .55,
    cursor: 'not-allowed',
    transform: 'none',
    boxShadow: 'none'
  } : null, style);
  return /*#__PURE__*/React.createElement(Tag, _extends({
    style: css,
    disabled: Tag === 'button' ? disabled : undefined,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false)
  }, rest), icon ? /*#__PURE__*/React.createElement("span", {
    className: 'dashicons dashicons-' + icon,
    style: {
      fontSize: '16px',
      width: '16px',
      height: '16px',
      lineHeight: 1
    }
  }) : null, children);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/IconButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Square icon-only button — the row-action button used in the keyword / ideas tables. */
function IconButton({
  icon,
  tone = 'default',
  size = 'md',
  label,
  style,
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const pad = size === 'sm' ? '6px' : size === 'lg' ? '12px' : '8px';
  const tones = {
    default: {
      color: 'var(--fyn-gray-700)',
      borderColor: 'var(--fyn-gray-300)'
    },
    brand: {
      color: 'var(--color-primary)',
      borderColor: 'var(--fyn-gray-300)'
    },
    danger: {
      color: 'var(--fyn-danger)',
      borderColor: 'var(--fyn-danger)'
    }
  };
  const hoverTones = {
    default: {
      background: 'var(--fyn-gray-100)'
    },
    brand: {
      background: 'var(--fyn-purple-tint)',
      borderColor: 'var(--color-primary)'
    },
    danger: {
      background: 'var(--fyn-danger-light)'
    }
  };
  const css = Object.assign({
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: pad,
    background: 'var(--fyn-white)',
    border: '1px solid',
    borderRadius: 'var(--radius-sm)',
    cursor: 'pointer',
    transition: 'var(--transition)',
    lineHeight: 1
  }, tones[tone], hover ? hoverTones[tone] : null, style);
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    "aria-label": label,
    title: label,
    style: css,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false)
  }, rest), /*#__PURE__*/React.createElement("span", {
    className: 'dashicons dashicons-' + icon,
    style: {
      fontSize: size === 'sm' ? '14px' : '18px',
      width: '1em',
      height: '1em',
      lineHeight: 1
    }
  }));
}
Object.assign(__ds_scope, { IconButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/IconButton.jsx", error: String((e && e.message) || e) }); }

// components/core/Spinner.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Inline loading indicator with optional label — the '.aiseo-loading' pattern. */
function Spinner({
  label,
  size = 16,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("span", _extends({
    style: Object.assign({
      display: 'inline-flex',
      alignItems: 'center',
      gap: '8px',
      color: 'var(--text-muted)',
      fontSize: 'var(--text-xs)'
    }, style)
  }, rest), /*#__PURE__*/React.createElement("style", null, '@keyframes fyn-spin{to{transform:rotate(360deg)}}'), /*#__PURE__*/React.createElement("span", {
    style: {
      width: size + 'px',
      height: size + 'px',
      border: '2px solid var(--fyn-gray-200)',
      borderTopColor: 'var(--color-primary)',
      borderRadius: 'var(--radius-round)',
      animation: 'fyn-spin .7s linear infinite',
      display: 'inline-block'
    }
  }), label);
}
Object.assign(__ds_scope, { Spinner });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Spinner.jsx", error: String((e && e.message) || e) }); }

// components/core/StatusPill.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const tones = {
  success: {
    background: 'var(--fyn-success-light)',
    color: '#155724',
    dot: 'var(--fyn-success)'
  },
  warning: {
    background: 'var(--fyn-warning-light)',
    color: '#856404',
    dot: 'var(--fyn-warning)'
  },
  danger: {
    background: 'var(--fyn-danger-light)',
    color: '#721c24',
    dot: 'var(--fyn-danger)'
  },
  info: {
    background: 'var(--fyn-info-light)',
    color: '#0c5460',
    dot: 'var(--fyn-info)'
  }
};

/** Status chip with a leading dot — connection state, license state, job state. */
function StatusPill({
  tone = 'info',
  children,
  style,
  ...rest
}) {
  const t = tones[tone] || tones.info;
  const css = Object.assign({
    display: 'inline-flex',
    alignItems: 'center',
    gap: '6px',
    padding: '6px 12px',
    borderRadius: 'var(--radius-pill)',
    fontSize: 'var(--text-2xs)',
    fontWeight: 'var(--weight-semibold)',
    background: t.background,
    color: t.color
  }, style);
  return /*#__PURE__*/React.createElement("span", _extends({
    style: css
  }, rest), /*#__PURE__*/React.createElement("span", {
    style: {
      width: '8px',
      height: '8px',
      borderRadius: 'var(--radius-round)',
      background: t.dot,
      flexShrink: 0
    }
  }), children);
}
Object.assign(__ds_scope, { StatusPill });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/StatusPill.jsx", error: String((e && e.message) || e) }); }

// components/core/Tooltip.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Dark tooltip above the trigger — the '.aiseo-tooltip' pattern. */
function Tooltip({
  text,
  children,
  style,
  ...rest
}) {
  const [open, setOpen] = React.useState(false);
  return /*#__PURE__*/React.createElement("span", _extends({
    style: Object.assign({
      position: 'relative',
      display: 'inline-flex',
      cursor: 'help'
    }, style),
    onMouseEnter: () => setOpen(true),
    onMouseLeave: () => setOpen(false)
  }, rest), children, /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      bottom: open ? 'calc(100% + 8px)' : '100%',
      left: '50%',
      transform: 'translateX(-50%)',
      background: 'var(--fyn-ink)',
      color: 'var(--text-inverse)',
      padding: '8px 12px',
      borderRadius: 'var(--radius-sm)',
      fontSize: 'var(--text-2xs)',
      whiteSpace: 'nowrap',
      opacity: open ? 1 : 0,
      visibility: open ? 'visible' : 'hidden',
      transition: 'var(--transition)',
      zIndex: 1000,
      pointerEvents: 'none'
    }
  }, text));
}
Object.assign(__ds_scope, { Tooltip });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Tooltip.jsx", error: String((e && e.message) || e) }); }

// components/data/AnalysisList.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const tones = {
  good: {
    border: 'var(--fyn-success)',
    background: 'var(--fyn-success-light)'
  },
  warning: {
    border: 'var(--fyn-warning)',
    background: 'var(--fyn-warning-light)'
  },
  error: {
    border: 'var(--fyn-danger)',
    background: 'var(--fyn-danger-light)'
  },
  info: {
    border: 'var(--fyn-info)',
    background: 'var(--fyn-info-light)'
  },
  neutral: {
    border: 'var(--fyn-gray-300)',
    background: 'transparent'
  }
};

/** SEO check list — left-rule tinted rows, one per finding. */
function AnalysisList({
  items = [],
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("ul", _extends({
    style: Object.assign({
      listStyle: 'none',
      padding: 0,
      margin: 0
    }, style)
  }, rest), items.map((it, i) => {
    const t = tones[it.tone] || tones.neutral;
    return /*#__PURE__*/React.createElement("li", {
      key: i,
      style: {
        padding: '10px 12px',
        margin: '4px 0',
        borderLeft: '3px solid ' + t.border,
        background: t.background,
        borderRadius: '0 var(--radius-sm) var(--radius-sm) 0',
        fontSize: 'var(--text-xs)',
        display: 'flex',
        justifyContent: 'space-between',
        gap: '10px',
        alignItems: 'center'
      }
    }, /*#__PURE__*/React.createElement("span", null, it.label), it.meta ? /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--text-muted)',
        flexShrink: 0
      }
    }, it.meta) : null);
  }));
}
Object.assign(__ds_scope, { AnalysisList });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/AnalysisList.jsx", error: String((e && e.message) || e) }); }

// components/data/DataTable.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Dense data table: uppercase header row, 1px row separators, gray row hover. */
function DataTable({
  columns = [],
  rows = [],
  compact = false,
  style,
  ...rest
}) {
  const [hover, setHover] = React.useState(-1);
  const pad = compact ? '12px 8px' : '15px';
  return /*#__PURE__*/React.createElement("table", _extends({
    style: Object.assign({
      width: '100%',
      borderCollapse: 'separate',
      borderSpacing: 0,
      fontSize: 'var(--text-xs)'
    }, style)
  }, rest), /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, columns.map((c, i) => /*#__PURE__*/React.createElement("th", {
    key: i,
    style: {
      background: 'var(--fyn-gray-50)',
      padding: pad,
      textAlign: c.align || 'left',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--text-muted)',
      fontSize: 'var(--text-2xs)',
      textTransform: 'uppercase',
      letterSpacing: 'var(--tracking-wide)',
      borderBottom: '2px solid var(--fyn-gray-200)',
      width: c.width,
      whiteSpace: 'nowrap'
    }
  }, c.label)))), /*#__PURE__*/React.createElement("tbody", null, rows.map((r, ri) => /*#__PURE__*/React.createElement("tr", {
    key: ri,
    onMouseEnter: () => setHover(ri),
    onMouseLeave: () => setHover(-1)
  }, columns.map((c, ci) => /*#__PURE__*/React.createElement("td", {
    key: ci,
    style: {
      padding: pad,
      borderBottom: '1px solid var(--border-subtle)',
      verticalAlign: 'middle',
      textAlign: c.align || 'left',
      background: hover === ri ? 'var(--fyn-gray-50)' : 'transparent',
      color: 'var(--text-body)',
      transition: 'background .15s ease'
    }
  }, r[c.key]))))));
}
Object.assign(__ds_scope, { DataTable });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/DataTable.jsx", error: String((e && e.message) || e) }); }

// components/data/FeatureList.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Licensed-feature list: green tick rows for included features, muted rows for locked ones. */
function FeatureList({
  items = [],
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("ul", _extends({
    style: Object.assign({
      listStyle: 'none',
      padding: 0,
      margin: 0
    }, style)
  }, rest), items.map((it, i) => {
    const locked = it.locked;
    return /*#__PURE__*/React.createElement("li", {
      key: i,
      style: {
        padding: '10px 12px',
        margin: '4px 0',
        borderRadius: 'var(--radius-sm)',
        borderLeft: '3px solid ' + (locked ? 'var(--fyn-gray-300)' : 'var(--fyn-success)'),
        background: locked ? 'var(--fyn-gray-50)' : 'var(--fyn-success-light)',
        color: locked ? 'var(--text-muted)' : 'var(--text-body)',
        fontSize: 'var(--text-ui)',
        display: 'flex',
        alignItems: 'center',
        gap: '10px'
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        color: locked ? 'var(--fyn-gray-400)' : 'var(--fyn-success)',
        fontWeight: 'var(--weight-bold)'
      }
    }, locked ? '✕' : '✓'), typeof it === 'string' ? it : it.label);
  }));
}
Object.assign(__ds_scope, { FeatureList });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/FeatureList.jsx", error: String((e && e.message) || e) }); }

// components/data/Pagination.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Numbered pager used under long tables. */
function Pagination({
  page = 1,
  pages = 1,
  info,
  onChange,
  style,
  ...rest
}) {
  const go = p => {
    if (onChange && p >= 1 && p <= pages) onChange(p);
  };
  const nums = [];
  for (let i = 1; i <= pages; i++) {
    if (i === 1 || i === pages || Math.abs(i - page) <= 1) nums.push(i);else if (nums[nums.length - 1] !== '…') nums.push('…');
  }
  const btn = on => ({
    padding: '8px 12px',
    border: '1px solid ' + (on ? 'var(--color-primary)' : 'var(--fyn-gray-300)'),
    background: on ? 'var(--color-primary)' : 'var(--fyn-white)',
    color: on ? 'var(--text-inverse)' : 'var(--text-body)',
    borderRadius: 'var(--radius-sm)',
    cursor: 'pointer',
    minWidth: '36px',
    fontFamily: 'var(--font-core)',
    fontSize: 'var(--text-xs)'
  });
  return /*#__PURE__*/React.createElement("div", _extends({
    style: Object.assign({
      display: 'flex',
      justifyContent: info ? 'space-between' : 'center',
      alignItems: 'center',
      gap: '8px',
      marginTop: '30px',
      paddingTop: info ? '20px' : 0,
      borderTop: info ? '1px solid var(--fyn-gray-200)' : 'none'
    }, style)
  }, rest), info ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-xs)',
      color: 'var(--text-muted)'
    }
  }, info) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: '5px',
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    style: btn(false),
    onClick: () => go(page - 1)
  }, "\u2039"), nums.map((n, i) => n === '…' ? /*#__PURE__*/React.createElement("span", {
    key: i,
    style: {
      padding: '8px',
      color: 'var(--text-muted)'
    }
  }, "\u2026") : /*#__PURE__*/React.createElement("button", {
    key: i,
    type: "button",
    style: btn(n === page),
    onClick: () => go(n)
  }, n)), /*#__PURE__*/React.createElement("button", {
    type: "button",
    style: btn(false),
    onClick: () => go(page + 1)
  }, "\u203A")));
}
Object.assign(__ds_scope, { Pagination });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Pagination.jsx", error: String((e && e.message) || e) }); }

// components/data/ProgressBar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const fills = {
  brand: 'var(--fyn-gradient-flat)',
  success: 'linear-gradient(90deg,#10b981,#20c997)',
  warning: 'linear-gradient(90deg,#f59e0b,#ff9800)',
  danger: 'linear-gradient(90deg,#ef4444,#e91e63)'
};

/** Thin track + gradient fill, with an optional label/value row above it. */
function ProgressBar({
  value = 0,
  tone = 'brand',
  label,
  hint,
  height = 8,
  style,
  ...rest
}) {
  const pct = Math.max(0, Math.min(100, value));
  return /*#__PURE__*/React.createElement("div", _extends({
    style: Object.assign({}, style)
  }, rest), label || hint ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      marginBottom: '3px',
      fontSize: 'var(--text-xs)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 'var(--weight-medium)',
      color: 'var(--text-body)'
    }
  }, label), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--text-muted)'
    }
  }, hint)) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--fyn-gray-200)',
      borderRadius: '10px',
      height: height + 'px',
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      height: '100%',
      width: pct + '%',
      borderRadius: '10px',
      background: fills[tone] || fills.brand,
      transition: 'width .3s ease'
    }
  })));
}
Object.assign(__ds_scope, { ProgressBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/ProgressBar.jsx", error: String((e && e.message) || e) }); }

// components/data/ScoreRing.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Circular SEO score dial. Colour follows the product thresholds: 80+ green, 50+ amber, else red. */
function ScoreRing({
  score = 0,
  size = 160,
  label,
  caption,
  style,
  ...rest
}) {
  const color = score >= 80 ? 'var(--fyn-success)' : score >= 50 ? 'var(--fyn-warning)' : 'var(--fyn-danger)';
  return /*#__PURE__*/React.createElement("div", _extends({
    style: Object.assign({
      textAlign: 'center'
    }, style)
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      width: size + 'px',
      height: size + 'px',
      margin: '0 auto 15px'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 36 36",
    style: {
      width: '100%',
      height: '100%',
      transform: 'rotate(-90deg)'
    }
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "18",
    cy: "18",
    r: "15.9",
    fill: "none",
    stroke: "var(--fyn-gray-200)",
    strokeWidth: "3"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "18",
    cy: "18",
    r: "15.9",
    fill: "none",
    stroke: color,
    strokeWidth: "3",
    strokeDasharray: score + ', 100',
    strokeLinecap: "round",
    style: {
      transition: 'stroke-dasharray 1s ease'
    }
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: '50%',
      left: '50%',
      transform: 'translate(-50%,-50%)',
      fontSize: Math.round(size * .225) + 'px',
      fontWeight: 'var(--weight-bold)',
      color: color
    }
  }, score)), label ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-sm)',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--text-heading)'
    }
  }, label) : null, caption ? /*#__PURE__*/React.createElement("div", {
    style: {
      color: 'var(--text-muted)',
      fontSize: 'var(--text-xs)'
    }
  }, caption) : null);
}
Object.assign(__ds_scope, { ScoreRing });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/ScoreRing.jsx", error: String((e && e.message) || e) }); }

// components/data/SerpPreview.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Google result preview for the SEO title / meta description editor. */
function SerpPreview({
  title,
  url,
  description,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    style: Object.assign({
      border: '1px solid var(--border-default)',
      padding: '16px',
      background: 'var(--surface-card)',
      borderRadius: 'var(--radius-lg)',
      margin: '15px 0'
    }, style)
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      color: 'var(--fyn-serp-title)',
      fontSize: 'var(--text-base)',
      lineHeight: 'var(--leading-snug)',
      cursor: 'pointer'
    }
  }, title), /*#__PURE__*/React.createElement("div", {
    style: {
      color: 'var(--fyn-serp-url)',
      fontSize: 'var(--text-xs)',
      margin: '2px 0'
    }
  }, url), /*#__PURE__*/React.createElement("div", {
    style: {
      color: 'var(--fyn-serp-desc)',
      fontSize: 'var(--text-ui)',
      lineHeight: 'var(--leading-normal)'
    }
  }, description));
}
Object.assign(__ds_scope, { SerpPreview });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/SerpPreview.jsx", error: String((e && e.message) || e) }); }

// components/data/StatCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const accents = {
  brand: 'var(--fyn-gradient)',
  success: 'linear-gradient(90deg,#10b981,#20c997)',
  warning: 'linear-gradient(90deg,#f59e0b,#ff9800)',
  danger: 'linear-gradient(90deg,#ef4444,#e91e63)',
  info: 'linear-gradient(90deg,#3b82f6,#03a9f4)'
};

/** KPI tile: big number, label, optional delta chip and top accent bar. */
function StatCard({
  value,
  label,
  tone = 'brand',
  icon,
  change,
  changeTone = 'positive',
  align = 'left',
  style,
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const css = Object.assign({
    background: 'var(--surface-card)',
    borderRadius: 'var(--radius-lg)',
    padding: '25px',
    boxShadow: hover ? 'var(--shadow-card-hover)' : 'var(--shadow-card)',
    transform: hover ? 'var(--lift-md)' : 'none',
    transition: 'transform .2s,box-shadow .2s',
    position: 'relative',
    overflow: 'hidden',
    textAlign: align
  }, style);
  return /*#__PURE__*/React.createElement("div", _extends({
    style: css,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false)
  }, rest), /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      top: 0,
      left: 0,
      right: 0,
      height: '4px',
      background: accents[tone] || accents.brand
    }
  }), icon ? /*#__PURE__*/React.createElement("span", {
    style: {
      width: '48px',
      height: '48px',
      borderRadius: 'var(--radius-lg)',
      background: accents[tone] || accents.brand,
      color: 'var(--text-inverse)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      marginBottom: '15px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: 'dashicons dashicons-' + icon,
    style: {
      fontSize: '24px',
      width: '24px',
      height: '24px'
    }
  })) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-3xl)',
      fontWeight: 'var(--weight-bold)',
      color: 'var(--text-heading)',
      lineHeight: 1,
      marginBottom: '8px'
    }
  }, value), /*#__PURE__*/React.createElement("div", {
    style: {
      color: 'var(--text-muted)',
      fontSize: 'var(--text-2xs)',
      fontWeight: 'var(--weight-medium)',
      textTransform: 'uppercase',
      letterSpacing: 'var(--tracking-wide)'
    }
  }, label), change ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '5px',
      marginTop: '10px',
      fontSize: 'var(--text-xs)',
      fontWeight: 'var(--weight-semibold)',
      padding: '4px 10px',
      borderRadius: 'var(--radius-pill)',
      background: changeTone === 'positive' ? 'var(--fyn-success-light)' : 'var(--fyn-danger-light)',
      color: changeTone === 'positive' ? '#155724' : '#721c24'
    }
  }, change) : null);
}
Object.assign(__ds_scope, { StatCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/StatCard.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Alert.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const tones = {
  success: {
    background: 'var(--fyn-success-light)',
    rule: 'var(--fyn-success)',
    icon: 'yes-alt'
  },
  warning: {
    background: 'var(--fyn-warning-light)',
    rule: 'var(--fyn-warning)',
    icon: 'warning'
  },
  danger: {
    background: 'var(--fyn-danger-light)',
    rule: 'var(--fyn-danger)',
    icon: 'dismiss'
  },
  info: {
    background: 'var(--fyn-info-light)',
    rule: 'var(--fyn-info)',
    icon: 'info'
  }
};

/** Inline notice with a 4px left rule — the product's admin notice. */
function Alert({
  tone = 'info',
  title,
  children,
  action,
  style,
  ...rest
}) {
  const t = tones[tone] || tones.info;
  return /*#__PURE__*/React.createElement("div", _extends({
    style: Object.assign({
      padding: '15px 20px',
      borderRadius: 'var(--radius-md)',
      marginBottom: '20px',
      display: 'flex',
      alignItems: 'flex-start',
      gap: '15px',
      background: t.background,
      borderLeft: '4px solid ' + t.rule
    }, style)
  }, rest), /*#__PURE__*/React.createElement("span", {
    className: 'dashicons dashicons-' + t.icon,
    style: {
      color: t.rule,
      fontSize: '20px',
      width: '20px',
      height: '20px',
      flexShrink: 0
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      fontSize: 'var(--text-body)',
      color: 'var(--text-body)'
    }
  }, title ? /*#__PURE__*/React.createElement("strong", {
    style: {
      display: 'block',
      marginBottom: '4px',
      color: 'var(--text-heading)'
    }
  }, title) : null, children), action);
}
Object.assign(__ds_scope, { Alert });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Alert.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Modal.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Centred dialog with dimmed overlay, header / body / footer bands. */
function Modal({
  open = true,
  title,
  size = 'md',
  onClose,
  footer,
  children,
  style,
  ...rest
}) {
  if (!open) return null;
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      position: 'fixed',
      inset: 0,
      zIndex: 100000,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    onClick: onClose,
    style: {
      position: 'absolute',
      inset: 0,
      background: 'rgba(0,0,0,.5)'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: Object.assign({
      position: 'relative',
      background: 'var(--surface-card)',
      borderRadius: 'var(--radius-lg)',
      width: '90%',
      maxWidth: size === 'lg' ? '800px' : '500px',
      maxHeight: '90vh',
      overflowY: 'auto',
      boxShadow: 'var(--shadow-lg)'
    }, style)
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '20px 25px',
      borderBottom: '1px solid var(--border-default)',
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      gap: '10px'
    }
  }, /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      fontSize: 'var(--text-base)',
      fontWeight: 'var(--weight-semibold)'
    }
  }, title), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onClose,
    "aria-label": "Close",
    style: {
      background: 'none',
      border: 'none',
      fontSize: '24px',
      lineHeight: 1,
      cursor: 'pointer',
      color: 'var(--text-muted)'
    }
  }, "\xD7")), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '25px'
    }
  }, children), footer ? /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '20px 25px',
      borderTop: '1px solid var(--border-default)',
      display: 'flex',
      justifyContent: 'flex-end',
      gap: '10px'
    }
  }, footer) : null));
}
Object.assign(__ds_scope, { Modal });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Modal.jsx", error: String((e && e.message) || e) }); }

// components/forms/FormField.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Labelled form control (input / select / textarea) with description and 2px focus border. */
function FormField({
  label,
  as = 'input',
  description,
  hint,
  options = [],
  rows = 4,
  style,
  ...rest
}) {
  const [focus, setFocus] = React.useState(false);
  const control = {
    width: '100%',
    padding: '12px 16px',
    border: '2px solid ' + (focus ? 'var(--border-focus)' : 'var(--border-default)'),
    borderRadius: 'var(--radius-sm)',
    fontSize: 'var(--text-body)',
    fontFamily: 'var(--font-core)',
    color: 'var(--text-body)',
    background: 'var(--fyn-white)',
    outline: 'none',
    boxShadow: focus ? 'var(--focus-ring)' : 'none',
    transition: 'var(--transition)',
    boxSizing: 'border-box'
  };
  const handlers = {
    onFocus: () => setFocus(true),
    onBlur: () => setFocus(false)
  };
  return /*#__PURE__*/React.createElement("div", {
    style: Object.assign({
      marginBottom: '24px'
    }, style)
  }, label ? /*#__PURE__*/React.createElement("label", {
    style: {
      display: 'block',
      fontSize: 'var(--text-ui)',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--fyn-gray-700)',
      marginBottom: '8px'
    }
  }, label) : null, as === 'select' ? /*#__PURE__*/React.createElement("select", _extends({
    style: control
  }, handlers, rest), options.map(o => /*#__PURE__*/React.createElement("option", {
    key: o.value !== undefined ? o.value : o,
    value: o.value !== undefined ? o.value : o
  }, o.label !== undefined ? o.label : o))) : as === 'textarea' ? /*#__PURE__*/React.createElement("textarea", _extends({
    rows: rows,
    style: Object.assign({}, control, {
      resize: 'vertical',
      lineHeight: 'var(--leading-relaxed)'
    })
  }, handlers, rest)) : /*#__PURE__*/React.createElement("input", _extends({
    style: control
  }, handlers, rest)), description ? /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 'var(--text-xs)',
      color: 'var(--text-muted)',
      margin: '6px 0 0',
      fontStyle: 'italic'
    }
  }, description) : null, hint);
}
Object.assign(__ds_scope, { FormField });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/FormField.jsx", error: String((e && e.message) || e) }); }

// components/forms/LicenseKeyField.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Monospace, letter-spaced licence key display or input with a copy affordance. */
function LicenseKeyField({
  label,
  value,
  readOnly = true,
  onCopy,
  style,
  ...rest
}) {
  const [copied, setCopied] = React.useState(false);
  const copy = () => {
    setCopied(true);
    if (onCopy) onCopy(value);
    window.setTimeout(() => setCopied(false), 1400);
  };
  return /*#__PURE__*/React.createElement("div", {
    style: Object.assign({
      marginBottom: '20px'
    }, style)
  }, label ? /*#__PURE__*/React.createElement("label", {
    style: {
      display: 'block',
      fontSize: 'var(--text-xs)',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--text-muted)',
      textTransform: 'uppercase',
      letterSpacing: 'var(--tracking-wide)',
      marginBottom: '6px'
    }
  }, label) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: '8px',
      alignItems: 'stretch'
    }
  }, /*#__PURE__*/React.createElement("div", _extends({
    style: {
      flex: 1,
      fontSize: 'var(--text-sm)',
      color: 'var(--text-heading)',
      fontFamily: 'var(--font-mono)',
      letterSpacing: '1px',
      background: 'var(--fyn-gray-50)',
      padding: '12px 16px',
      borderRadius: 'var(--radius-sm)',
      border: '1px solid var(--border-default)',
      overflow: 'hidden',
      textOverflow: 'ellipsis'
    }
  }, rest), value), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: copy,
    style: {
      padding: '0 14px',
      border: '1px solid var(--border-default)',
      background: 'var(--fyn-white)',
      borderRadius: 'var(--radius-sm)',
      cursor: 'pointer',
      color: copied ? 'var(--fyn-success)' : 'var(--text-muted)',
      fontSize: 'var(--text-xs)',
      fontFamily: 'var(--font-core)'
    }
  }, copied ? 'Copied' : 'Copy')));
}
Object.assign(__ds_scope, { LicenseKeyField });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/LicenseKeyField.jsx", error: String((e && e.message) || e) }); }

// components/layout/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** The workhorse surface: white, 12px radius, soft shadow, optional header strip. */
function Card({
  title,
  icon,
  action,
  glass = false,
  padding,
  children,
  style,
  ...rest
}) {
  const css = Object.assign({
    background: glass ? 'var(--surface-card-glass)' : 'var(--surface-card)',
    backdropFilter: glass ? 'var(--blur-glass)' : undefined,
    borderRadius: 'var(--radius-lg)',
    boxShadow: 'var(--shadow-lg)',
    overflow: 'hidden'
  }, style);
  return /*#__PURE__*/React.createElement("section", _extends({
    style: css
  }, rest), title ? /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '20px 25px',
      borderBottom: '1px solid var(--border-subtle)',
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      gap: 'var(--gap-tight)'
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: 0,
      fontSize: 'var(--text-base)',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--text-heading)',
      display: 'flex',
      alignItems: 'center',
      gap: '8px'
    }
  }, icon ? /*#__PURE__*/React.createElement("span", {
    className: 'dashicons dashicons-' + icon,
    style: {
      color: 'var(--color-primary)'
    }
  }) : null, title), action) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: padding || 'var(--pad-card)'
    }
  }, children));
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/Card.jsx", error: String((e && e.message) || e) }); }

// components/layout/EmptyState.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Centred empty state: round gray icon well, heading, one line of copy, one action. */
function EmptyState({
  icon = 'search',
  title,
  description,
  action,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    style: Object.assign({
      textAlign: 'center',
      padding: '60px 20px'
    }, style)
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      width: '80px',
      height: '80px',
      background: 'var(--fyn-gray-50)',
      borderRadius: 'var(--radius-round)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      margin: '0 auto 20px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: 'dashicons dashicons-' + icon,
    style: {
      fontSize: '40px',
      width: '40px',
      height: '40px',
      color: 'var(--color-primary)'
    }
  })), /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: '0 0 10px',
      color: 'var(--text-heading)'
    }
  }, title), description ? /*#__PURE__*/React.createElement("p", {
    style: {
      color: 'var(--text-muted)',
      margin: '0 0 20px'
    }
  }, description) : null, action);
}
Object.assign(__ds_scope, { EmptyState });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/EmptyState.jsx", error: String((e && e.message) || e) }); }

// components/layout/NavPills.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Rounded pill filter row — status filters and secondary navigation. */
function NavPills({
  items = [],
  value,
  onChange,
  style,
  ...rest
}) {
  const [internal, setInternal] = React.useState(items[0] && items[0].id);
  const active = value !== undefined ? value : internal;
  const [hover, setHover] = React.useState(null);
  const select = id => {
    setInternal(id);
    if (onChange) onChange(id);
  };
  return /*#__PURE__*/React.createElement("div", _extends({
    style: Object.assign({
      display: 'flex',
      gap: 'var(--gap-tight)',
      flexWrap: 'wrap'
    }, style)
  }, rest), items.map(it => {
    const on = it.id === active || hover === it.id;
    return /*#__PURE__*/React.createElement("button", {
      key: it.id,
      type: "button",
      onClick: () => select(it.id),
      onMouseEnter: () => setHover(it.id),
      onMouseLeave: () => setHover(null),
      style: {
        padding: '8px 20px',
        borderRadius: 'var(--radius-pill)',
        cursor: 'pointer',
        fontFamily: 'var(--font-core)',
        fontSize: 'var(--text-xs)',
        fontWeight: 'var(--weight-medium)',
        border: '1px solid ' + (it.tone === 'danger' ? 'var(--fyn-danger)' : 'var(--color-primary)'),
        background: on ? it.tone === 'danger' ? 'var(--fyn-danger)' : 'var(--color-primary)' : 'var(--fyn-white)',
        color: on ? 'var(--text-inverse)' : it.tone === 'danger' ? 'var(--fyn-danger)' : 'var(--color-primary)',
        transition: 'var(--transition)'
      }
    }, it.label, it.count !== undefined ? /*#__PURE__*/React.createElement("strong", {
      style: {
        marginLeft: '5px'
      }
    }, it.count) : null);
  }));
}
Object.assign(__ds_scope, { NavPills });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/NavPills.jsx", error: String((e && e.message) || e) }); }

// components/layout/PageHeader.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Dark full-bleed page header that opens every Fyndable admin screen. */
function PageHeader({
  title,
  description,
  logo,
  actions,
  style,
  ...rest
}) {
  const css = Object.assign({
    background: 'var(--surface-header)',
    color: 'var(--text-inverse)',
    padding: 'var(--pad-header)',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 'var(--gap-grid)'
  }, style);
  return /*#__PURE__*/React.createElement("header", _extends({
    style: css
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--gap-default)',
      minWidth: 0
    }
  }, logo ? /*#__PURE__*/React.createElement("img", {
    src: logo,
    alt: "",
    style: {
      height: '34px',
      width: 'auto',
      flexShrink: 0
    }
  }) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      fontSize: 'var(--text-xl)',
      fontWeight: 'var(--weight-bold)',
      color: 'var(--text-inverse)',
      letterSpacing: 'var(--tracking-tight)'
    }
  }, title), description ? /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '10px 0 0',
      opacity: .8,
      fontSize: 'var(--text-ui)'
    }
  }, description) : null)), actions ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--gap-tight)',
      flexShrink: 0
    }
  }, actions) : null);
}
Object.assign(__ds_scope, { PageHeader });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/PageHeader.jsx", error: String((e && e.message) || e) }); }

// components/layout/QuickAction.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Compact icon-over-label shortcut tile from the dashboard's Quick Actions row. */
function QuickAction({
  label,
  icon,
  href = '#',
  style,
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const css = Object.assign({
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    padding: '20px',
    background: hover ? 'var(--surface-card)' : 'var(--fyn-gray-50)',
    borderRadius: 'var(--radius-lg)',
    textDecoration: 'none',
    color: 'var(--text-body)',
    transition: 'var(--transition)',
    border: '2px solid transparent'
  }, hover ? {
    borderColor: 'var(--color-primary)',
    transform: 'var(--lift-md)',
    boxShadow: '0 4px 12px rgba(143,57,172,.2)'
  } : null, style);
  return /*#__PURE__*/React.createElement("a", _extends({
    href: href,
    style: css,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false)
  }, rest), /*#__PURE__*/React.createElement("span", {
    style: {
      width: '48px',
      height: '48px',
      borderRadius: 'var(--radius-lg)',
      background: 'var(--fyn-gradient)',
      color: 'var(--text-inverse)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      marginBottom: '12px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: 'dashicons dashicons-' + icon,
    style: {
      fontSize: '24px',
      width: '24px',
      height: '24px'
    }
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-xs)',
      fontWeight: 'var(--weight-semibold)',
      textAlign: 'center'
    }
  }, label));
}
Object.assign(__ds_scope, { QuickAction });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/QuickAction.jsx", error: String((e && e.message) || e) }); }

// components/layout/Tabs.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Underline tab bar — the in-page section switcher (keywords, ideas, settings). */
function Tabs({
  items = [],
  value,
  onChange,
  style,
  ...rest
}) {
  const [internal, setInternal] = React.useState(items[0] && items[0].id);
  const active = value !== undefined ? value : internal;
  const select = id => {
    setInternal(id);
    if (onChange) onChange(id);
  };
  return /*#__PURE__*/React.createElement("div", _extends({
    style: Object.assign({
      display: 'flex',
      gap: '5px',
      borderBottom: '2px solid var(--fyn-gray-200)',
      marginBottom: 'var(--gap-section)'
    }, style)
  }, rest), items.map(it => {
    const on = it.id === active;
    return /*#__PURE__*/React.createElement("button", {
      key: it.id,
      type: "button",
      onClick: () => select(it.id),
      style: {
        padding: '15px 25px',
        background: 'none',
        border: 'none',
        borderBottom: '2px solid ' + (on ? 'var(--color-primary)' : 'transparent'),
        marginBottom: '-2px',
        cursor: 'pointer',
        fontFamily: 'var(--font-core)',
        fontSize: 'var(--text-ui)',
        fontWeight: 'var(--weight-semibold)',
        color: on ? 'var(--color-primary)' : 'var(--text-muted)',
        transition: 'var(--transition)',
        display: 'inline-flex',
        alignItems: 'center',
        gap: '6px'
      }
    }, it.label, it.count !== undefined ? /*#__PURE__*/React.createElement("span", {
      style: {
        background: on ? 'var(--fyn-purple-tint)' : 'var(--fyn-gray-200)',
        color: on ? 'var(--color-primary)' : 'var(--text-muted)',
        padding: '2px 8px',
        borderRadius: '10px',
        fontSize: 'var(--text-3xs)'
      }
    }, it.count) : null);
  }));
}
Object.assign(__ds_scope, { Tabs });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/Tabs.jsx", error: String((e && e.message) || e) }); }

// components/layout/ToolCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Clickable feature tile from the AI Tools grid: 2px border that turns purple and lifts on hover. */
function ToolCard({
  title,
  description,
  icon,
  badge,
  href = '#',
  style,
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const css = Object.assign({
    display: 'block',
    background: 'var(--surface-card)',
    border: '2px solid var(--border-default)',
    borderRadius: 'var(--radius-sm)',
    padding: '24px',
    transition: 'var(--transition)',
    textDecoration: 'none',
    color: 'inherit',
    cursor: 'pointer'
  }, hover ? {
    borderColor: 'var(--color-primary)',
    transform: 'var(--lift-lg)',
    boxShadow: 'var(--shadow-brand)'
  } : null, style);
  return /*#__PURE__*/React.createElement("a", _extends({
    href: href,
    style: css,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false)
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: '10px',
      marginBottom: '12px'
    }
  }, /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      fontSize: 'var(--text-base)',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--text-heading)',
      display: 'flex',
      alignItems: 'center',
      gap: '10px'
    }
  }, icon ? /*#__PURE__*/React.createElement("span", {
    className: 'dashicons dashicons-' + icon,
    style: {
      color: 'var(--color-primary)',
      fontSize: '20px',
      width: '20px',
      height: '20px'
    }
  }) : null, title), badge), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      fontSize: 'var(--text-ui)',
      color: 'var(--fyn-gray-600)',
      lineHeight: 'var(--leading-relaxed)'
    }
  }, description));
}
Object.assign(__ds_scope, { ToolCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/ToolCard.jsx", error: String((e && e.message) || e) }); }

// ui_kits/saas_dashboard/LicensesScreen.jsx
try { (() => {
function LicensesScreen() {
  const {
    Card,
    Button,
    Badge,
    IconButton,
    DataTable,
    NavPills,
    Pagination,
    FormField,
    Modal,
    LicenseKeyField
  } = window.FyndableDesignSystem_229a7a;
  const [filter, setFilter] = React.useState('all');
  const [generating, setGenerating] = React.useState(false);
  const [generated, setGenerated] = React.useState(null);
  const seed = [['fyndable.com', 'Business', 'active', '2027-01-14', '12.4k'], ['debakkerij-amsterdam.nl', 'Starter', 'active', '2026-11-02', '1.1k'], ['groenhof-tuinen.be', 'Professional', 'trial', '2026-08-09', '420'], ['keukenhof-projects.nl', 'Agency', 'active', '2027-03-30', '38.9k'], ['vandenberg-advocaten.nl', 'Starter', 'expired', '2026-06-30', '0'], ['sportcentrum-noord.nl', 'Professional', 'revoked', '2026-05-12', '0']];
  const tiers = {
    Starter: 'info',
    Professional: 'accent',
    Business: 'brand',
    Agency: 'success'
  };
  const states = {
    active: 'success',
    trial: 'warning',
    expired: 'danger',
    revoked: 'danger'
  };
  const visible = filter === 'all' ? seed : seed.filter(r => r[2] === filter);
  const rows = visible.map(r => ({
    site: /*#__PURE__*/React.createElement("strong", {
      style: {
        fontWeight: 'var(--weight-semibold)'
      }
    }, r[0]),
    tier: /*#__PURE__*/React.createElement(Badge, {
      tone: tiers[r[1]]
    }, r[1]),
    state: /*#__PURE__*/React.createElement(Badge, {
      tone: states[r[2]],
      uppercase: true
    }, r[2]),
    exp: r[3],
    calls: r[4],
    act: /*#__PURE__*/React.createElement("span", {
      style: {
        display: 'flex',
        gap: '5px'
      }
    }, /*#__PURE__*/React.createElement(IconButton, {
      icon: "edit",
      label: "Edit",
      size: "sm"
    }), /*#__PURE__*/React.createElement(IconButton, {
      icon: "update",
      label: "Renew",
      size: "sm",
      tone: "brand"
    }), /*#__PURE__*/React.createElement(IconButton, {
      icon: "dismiss",
      label: "Revoke",
      size: "sm",
      tone: "danger"
    }))
  }));
  const generate = () => {
    setGenerated('FYN-' + Math.random().toString(36).slice(2, 6).toUpperCase() + '-' + Math.random().toString(36).slice(2, 6).toUpperCase() + '-QW3R-7T5Y');
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gap: '20px',
      maxWidth: 'var(--width-content)'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "Licences",
    icon: "admin-network",
    action: /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      icon: "plus",
      onClick: () => {
        setGenerating(true);
        setGenerated(null);
      }
    }, "Generate licence")
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      gap: '15px',
      marginBottom: '20px',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement(NavPills, {
    value: filter,
    onChange: setFilter,
    items: [{
      id: 'all',
      label: 'All',
      count: seed.length
    }, {
      id: 'active',
      label: 'Active',
      count: seed.filter(r => r[2] === 'active').length
    }, {
      id: 'trial',
      label: 'Trial',
      count: seed.filter(r => r[2] === 'trial').length
    }, {
      id: 'expired',
      label: 'Expired',
      count: seed.filter(r => r[2] === 'expired').length
    }, {
      id: 'revoked',
      label: 'Revoked',
      count: seed.filter(r => r[2] === 'revoked').length,
      tone: 'danger'
    }]
  }), /*#__PURE__*/React.createElement(FormField, {
    placeholder: "Search site or key\u2026",
    style: {
      marginBottom: 0,
      width: '260px'
    }
  })), /*#__PURE__*/React.createElement(DataTable, {
    columns: [{
      key: 'site',
      label: 'Site'
    }, {
      key: 'tier',
      label: 'Tier',
      width: '140px'
    }, {
      key: 'state',
      label: 'State',
      width: '120px'
    }, {
      key: 'exp',
      label: 'Expires',
      width: '120px'
    }, {
      key: 'calls',
      label: 'API calls',
      width: '100px',
      align: 'right'
    }, {
      key: 'act',
      label: 'Actions',
      width: '130px'
    }],
    rows: rows
  }), /*#__PURE__*/React.createElement(Pagination, {
    page: 1,
    pages: 9,
    info: 'Showing 1–' + rows.length + ' of 248 licences'
  })), generating ? /*#__PURE__*/React.createElement(Modal, {
    title: "Generate licence",
    onClose: () => setGenerating(false),
    footer: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      onClick: () => setGenerating(false)
    }, "Close"), /*#__PURE__*/React.createElement(Button, {
      onClick: generate
    }, "Generate"))
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Customer site",
    placeholder: "https://klant.nl"
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Tier",
    as: "select",
    options: ['Starter', 'Professional', 'Business', 'Agency']
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Duration",
    as: "select",
    options: ['14-day trial', '1 year', '2 years'],
    style: {
      marginBottom: generated ? '20px' : 0
    }
  }), generated ? /*#__PURE__*/React.createElement(LicenseKeyField, {
    label: "New licence key",
    value: generated,
    style: {
      marginBottom: 0
    }
  }) : null) : null);
}
Object.assign(window, {
  LicensesScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/saas_dashboard/LicensesScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/saas_dashboard/OverviewScreen.jsx
try { (() => {
function OverviewScreen({
  onNavigate
}) {
  const {
    Card,
    StatCard,
    Button,
    DataTable,
    Badge,
    StatusPill,
    ProgressBar,
    Alert
  } = window.FyndableDesignSystem_229a7a;
  const tiers = {
    Starter: 'info',
    Professional: 'accent',
    Business: 'brand',
    Agency: 'success'
  };
  const recent = [['fyndable.com', 'Business', 'FYN-8H2K-4LM9', 'active'], ['debakkerij-amsterdam.nl', 'Starter', 'FYN-2QK4-8ZP1', 'active'], ['groenhof-tuinen.be', 'Professional', 'FYN-7YT3-1DM6', 'trial'], ['keukenhof-projects.nl', 'Agency', 'FYN-4WQ8-3NB2', 'active'], ['vandenberg-advocaten.nl', 'Starter', 'FYN-9LP2-6XC7', 'expired']].map(r => ({
    site: /*#__PURE__*/React.createElement("strong", {
      style: {
        fontWeight: 'var(--weight-semibold)'
      }
    }, r[0]),
    tier: /*#__PURE__*/React.createElement(Badge, {
      tone: tiers[r[1]]
    }, r[1]),
    key: /*#__PURE__*/React.createElement("span", {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 'var(--text-2xs)',
        letterSpacing: '.5px'
      }
    }, r[2]),
    status: r[3] === 'active' ? /*#__PURE__*/React.createElement(StatusPill, {
      tone: "success"
    }, "Active") : r[3] === 'trial' ? /*#__PURE__*/React.createElement(StatusPill, {
      tone: "warning"
    }, "Trial") : /*#__PURE__*/React.createElement(StatusPill, {
      tone: "danger"
    }, "Expired")
  }));
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gap: '20px',
      maxWidth: 'var(--width-content)'
    }
  }, /*#__PURE__*/React.createElement(Alert, {
    tone: "warning",
    title: "3 licences expire in the next 7 days",
    action: /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      onClick: () => onNavigate('licenses')
    }, "Review")
  }, "Renewal reminders were sent automatically."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(4,1fr)',
      gap: '20px'
    }
  }, /*#__PURE__*/React.createElement(StatCard, {
    value: "248",
    label: "Active licences",
    tone: "brand",
    icon: "admin-network",
    change: "+12"
  }), /*#__PURE__*/React.createElement(StatCard, {
    value: "182k",
    label: "API calls this month",
    tone: "info",
    icon: "chart-line",
    change: "+9.4%"
  }), /*#__PURE__*/React.createElement(StatCard, {
    value: "\u20AC 41.2k",
    label: "MRR",
    tone: "success",
    icon: "chart-bar",
    change: "+4.1%"
  }), /*#__PURE__*/React.createElement(StatCard, {
    value: "\u20AC 2.8k",
    label: "AI cost this month",
    tone: "warning",
    icon: "cloud",
    change: "+18%",
    changeTone: "negative"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 380px',
      gap: '20px'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "Recent activations",
    icon: "admin-network",
    padding: "0",
    action: /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary",
      onClick: () => onNavigate('licenses')
    }, "All licences")
  }, /*#__PURE__*/React.createElement(DataTable, {
    columns: [{
      key: 'site',
      label: 'Site'
    }, {
      key: 'tier',
      label: 'Tier',
      width: '140px'
    }, {
      key: 'key',
      label: 'Licence key',
      width: '170px'
    }, {
      key: 'status',
      label: 'Status',
      width: '150px'
    }],
    rows: recent
  })), /*#__PURE__*/React.createElement(Card, {
    title: "Revenue by tier",
    icon: "chart-bar"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gap: '14px'
    }
  }, /*#__PURE__*/React.createElement(ProgressBar, {
    label: "Agency \xB7 \u20AC499",
    hint: "18 sites",
    value: 22
  }), /*#__PURE__*/React.createElement(ProgressBar, {
    label: "Business \xB7 \u20AC299",
    hint: "46 sites",
    value: 56,
    tone: "success"
  }), /*#__PURE__*/React.createElement(ProgressBar, {
    label: "Professional \xB7 \u20AC199",
    hint: "94 sites",
    value: 78,
    tone: "brand"
  }), /*#__PURE__*/React.createElement(ProgressBar, {
    label: "Starter \xB7 \u20AC99",
    hint: "90 sites",
    value: 74,
    tone: "warning"
  })))));
}
Object.assign(window, {
  OverviewScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/saas_dashboard/OverviewScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/saas_dashboard/SaasChrome.jsx
try { (() => {
const saasMenu = [{
  id: 'overview',
  label: 'Overview'
}, {
  id: 'licenses',
  label: 'Licenses'
}, {
  id: 'tenants',
  label: 'Tenants & usage'
}, {
  id: 'whitelabel',
  label: 'White-label'
}, {
  id: 'settings',
  label: 'Settings'
}];
function SaasChrome({
  screen,
  onNavigate,
  children
}) {
  const {
    PageHeader,
    Button
  } = window.FyndableDesignSystem_229a7a;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: '100vh',
      background: 'var(--surface-page)',
      fontFamily: 'var(--font-core)'
    }
  }, /*#__PURE__*/React.createElement(PageHeader, {
    title: "Fyndable SaaS Dashboard",
    description: "Licences, tenants and API usage across every customer site.",
    logo: "../../assets/fyndable-mark.png",
    actions: /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      size: "sm",
      icon: "update"
    }, "Sync tenants")
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--fyn-white)',
      borderBottom: '1px solid var(--border-default)',
      padding: '0 40px',
      display: 'flex',
      gap: '5px'
    }
  }, saasMenu.map(m => {
    const on = m.id === screen;
    return /*#__PURE__*/React.createElement("button", {
      key: m.id,
      type: "button",
      onClick: () => onNavigate(m.id),
      style: {
        padding: '15px 25px',
        background: 'none',
        border: 'none',
        cursor: 'pointer',
        fontFamily: 'var(--font-core)',
        fontSize: 'var(--text-ui)',
        fontWeight: 'var(--weight-semibold)',
        borderBottom: '2px solid ' + (on ? 'var(--color-primary)' : 'transparent'),
        color: on ? 'var(--color-primary)' : 'var(--text-muted)',
        transition: 'var(--transition)'
      }
    }, m.label);
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '40px'
    }
  }, children));
}
Object.assign(window, {
  SaasChrome,
  saasMenu
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/saas_dashboard/SaasChrome.jsx", error: String((e && e.message) || e) }); }

// ui_kits/saas_dashboard/SaasDashboardApp.jsx
try { (() => {
function SaasApp() {
  const {
    Card,
    EmptyState,
    Button,
    FormField,
    LicenseKeyField
  } = window.FyndableDesignSystem_229a7a;
  const [screen, setScreen] = React.useState('overview');
  let view;
  if (screen === 'overview') view = /*#__PURE__*/React.createElement(window.OverviewScreen, {
    onNavigate: setScreen
  });else if (screen === 'licenses') view = /*#__PURE__*/React.createElement(window.LicensesScreen, null);else if (screen === 'tenants') view = /*#__PURE__*/React.createElement(window.TenantsScreen, null);else if (screen === 'whitelabel') view = /*#__PURE__*/React.createElement(window.WhiteLabelScreen, null);else view = /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--width-form)'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "Global settings",
    icon: "admin-settings"
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "AI provider",
    as: "select",
    options: ['Anthropic Claude', 'OpenAI GPT-4', 'Mistral']
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Default rate limit",
    defaultValue: "2000",
    description: "API calls per tenant per month on the Starter tier."
  }), /*#__PURE__*/React.createElement(LicenseKeyField, {
    label: "Gateway endpoint",
    value: "https://saas.fyndable.com/wp-json/fyndable-saas/v1"
  }), /*#__PURE__*/React.createElement(Button, null, "Save settings")));
  return /*#__PURE__*/React.createElement(window.SaasChrome, {
    screen: screen,
    onNavigate: setScreen
  }, view);
}
ReactDOM.createRoot(document.getElementById('root')).render(/*#__PURE__*/React.createElement(SaasApp, null));
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/saas_dashboard/SaasDashboardApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/saas_dashboard/TenantsScreen.jsx
try { (() => {
const tenants = [['Fyndable', 'fyndable.com', 'Business', [['AI calls', '12.4k / 20k', 62], ['SERP lookups', '3.1k / 5k', 62], ['Images', '180 / 500', 36]]], ['De Bakkerij', 'debakkerij-amsterdam.nl', 'Starter', [['AI calls', '1.1k / 2k', 55], ['SERP lookups', '210 / 500', 42], ['Images', '24 / 50', 48]]], ['Groenhof Tuinen', 'groenhof-tuinen.be', 'Professional', [['AI calls', '420 / 8k', 5], ['SERP lookups', '96 / 2k', 5], ['Images', '8 / 200', 4]]], ['Keukenhof Projects', 'keukenhof-projects.nl', 'Agency', [['AI calls', '38.9k / ∞', 88], ['SERP lookups', '9.8k / ∞', 74], ['Images', '1.2k / ∞', 61]]]];
function TenantsScreen() {
  const {
    Card,
    Badge,
    ProgressBar,
    Button,
    StatCard
  } = window.FyndableDesignSystem_229a7a;
  const tiers = {
    Starter: 'info',
    Professional: 'accent',
    Business: 'brand',
    Agency: 'success'
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gap: '20px',
      maxWidth: 'var(--width-content)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(3,1fr)',
      gap: '20px'
    }
  }, /*#__PURE__*/React.createElement(StatCard, {
    value: "248",
    label: "Tenants",
    tone: "brand",
    align: "center"
  }), /*#__PURE__*/React.createElement(StatCard, {
    value: "182k",
    label: "API calls (30d)",
    tone: "info",
    align: "center"
  }), /*#__PURE__*/React.createElement(StatCard, {
    value: "4",
    label: "Over quota",
    tone: "danger",
    align: "center"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(2,1fr)',
      gap: '20px'
    }
  }, tenants.map(t => /*#__PURE__*/React.createElement(Card, {
    key: t[1],
    padding: "20px"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'flex-start',
      gap: '10px'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: '0 0 4px',
      fontSize: 'var(--text-sm)',
      fontWeight: 'var(--weight-semibold)'
    }
  }, t[0]), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      color: 'var(--text-muted)',
      fontSize: 'var(--text-2xs)'
    }
  }, t[1])), /*#__PURE__*/React.createElement(Badge, {
    tone: tiers[t[2]]
  }, t[2])), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gap: '10px',
      marginTop: '15px'
    }
  }, t[3].map(u => /*#__PURE__*/React.createElement(ProgressBar, {
    key: u[0],
    label: u[0],
    hint: u[1],
    value: u[2],
    tone: u[2] > 80 ? 'danger' : u[2] > 55 ? 'warning' : 'success'
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: '15px',
      paddingTop: '15px',
      borderTop: '1px solid var(--border-subtle)',
      display: 'flex',
      justifyContent: 'flex-end',
      gap: '8px'
    }
  }, /*#__PURE__*/React.createElement(Button, {
    size: "sm",
    variant: "secondary"
  }, "Usage log"), /*#__PURE__*/React.createElement(Button, {
    size: "sm",
    variant: "outline"
  }, "Raise quota"))))));
}
Object.assign(window, {
  TenantsScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/saas_dashboard/TenantsScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/saas_dashboard/WhiteLabelScreen.jsx
try { (() => {
function WhiteLabelScreen() {
  const {
    Card,
    Button,
    FormField,
    Alert,
    PageHeader
  } = window.FyndableDesignSystem_229a7a;
  const [name, setName] = React.useState('Fyndable');
  const [saved, setSaved] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gap: '20px',
      maxWidth: 'var(--width-form)'
    }
  }, saved ? /*#__PURE__*/React.createElement(Alert, {
    tone: "success"
  }, "White-label settings saved. Client sites pick this up on the next licence check.") : null, /*#__PURE__*/React.createElement(Card, {
    title: "White-label branding",
    icon: "art"
  }, /*#__PURE__*/React.createElement("p", {
    style: {
      color: 'var(--text-muted)',
      fontSize: 'var(--text-ui)',
      marginTop: 0
    }
  }, "Applied to every client plugin that validates against this dashboard."), /*#__PURE__*/React.createElement(FormField, {
    label: "Company name",
    value: name,
    onChange: e => setName(e.target.value),
    description: "Replaces 'Fyndable' throughout the client plugin UI."
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      gap: '20px'
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Support email",
    defaultValue: "support@fyndable.com"
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Dashboard URL",
    defaultValue: "https://saas.fyndable.com"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Menu label",
    defaultValue: "Fyndable",
    description: "Shown in the WordPress admin menu."
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: '24px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'block',
      fontSize: 'var(--text-ui)',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--fyn-gray-700)',
      marginBottom: '8px'
    }
  }, "Header preview"), /*#__PURE__*/React.createElement("div", {
    style: {
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      border: '1px solid var(--border-default)'
    }
  }, /*#__PURE__*/React.createElement(PageHeader, {
    title: name || 'Fyndable',
    description: "Smart SEO for WordPress"
  }))), /*#__PURE__*/React.createElement(Button, {
    onClick: () => setSaved(true)
  }, "Save settings")));
}
Object.assign(window, {
  WhiteLabelScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/saas_dashboard/WhiteLabelScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/wp_client/AiToolsScreen.jsx
try { (() => {
const aiTools = [['Content Writer', 'edit', 'Generate a full SEO-optimized article from one keyword.'], ['Content Optimizer', 'chart-bar', 'Score and improve an existing post against the SERP.'], ['Content Brief', 'media-document', 'Build an outline from the top 10 ranking pages.'], ['AI Image Generator', 'format-image', 'Create featured and social images with alt text.'], ['Alt Text Generator', 'images-alt2', 'Write alt text for every image missing one.'], ['Content Repurposer', 'share', 'Turn one post into social, email and FAQ copy.'], ['Link Assistant', 'admin-links', 'Suggest internal links based on topic clusters.'], ['E-E-A-T Validator', 'awards', 'Check experience, expertise and trust signals.']];
function AiToolsScreen() {
  const {
    Card,
    ToolCard,
    QuickAction,
    Badge
  } = window.FyndableDesignSystem_229a7a;
  return /*#__PURE__*/React.createElement(window.PluginPage, {
    title: "AI Tools",
    description: "Every AI feature unlocked by your licence tier."
  }, /*#__PURE__*/React.createElement(Card, {
    glass: true,
    padding: "30px"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(4,1fr)',
      gap: '15px',
      marginBottom: '30px'
    }
  }, /*#__PURE__*/React.createElement(QuickAction, {
    icon: "lightbulb",
    label: "New idea"
  }), /*#__PURE__*/React.createElement(QuickAction, {
    icon: "admin-links",
    label: "Link assistant"
  }), /*#__PURE__*/React.createElement(QuickAction, {
    icon: "images-alt2",
    label: "Alt text"
  }), /*#__PURE__*/React.createElement(QuickAction, {
    icon: "search",
    label: "Site audit"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(2,1fr)',
      gap: '20px'
    }
  }, aiTools.map(([t, i, d], ix) => /*#__PURE__*/React.createElement(ToolCard, {
    key: t,
    icon: i,
    title: t,
    description: d,
    badge: ix > 5 ? /*#__PURE__*/React.createElement(Badge, {
      tone: "neutral"
    }, "Agency") : null
  })))));
}
Object.assign(window, {
  AiToolsScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/wp_client/AiToolsScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/wp_client/ClientApp.jsx
try { (() => {
function ClientApp() {
  const {
    Card,
    EmptyState,
    Button
  } = window.FyndableDesignSystem_229a7a;
  const [screen, setScreen] = React.useState('dashboard');
  const [connected, setConnected] = React.useState(true);
  const connect = () => {
    setConnected(true);
    setScreen('dashboard');
  };
  let view;
  if (screen === 'connection') view = /*#__PURE__*/React.createElement(window.ConnectionScreen, {
    connected: connected,
    onConnect: connect
  });else if (screen === 'dashboard' || screen === 'seodata') view = /*#__PURE__*/React.createElement(window.StatisticsScreen, null);else if (screen === 'tools') view = /*#__PURE__*/React.createElement(window.AiToolsScreen, null);else if (screen === 'gsc' || screen === 'google') view = /*#__PURE__*/React.createElement(window.SearchConsoleScreen, null);else if (screen === 'keywords') view = /*#__PURE__*/React.createElement(window.KeywordsScreen, null);else view = /*#__PURE__*/React.createElement(window.PluginPage, {
    title: (window.wpMenu.find(m => m.id === screen) || {}).label.replace(/^\S+\s/, ''),
    description: "This screen is part of the plugin but was not recreated in this kit."
  }, /*#__PURE__*/React.createElement(Card, {
    glass: true,
    padding: "30px"
  }, /*#__PURE__*/React.createElement(EmptyState, {
    icon: "admin-generic",
    title: "Not recreated",
    description: "Only Statistics, AI Tools, Search Console, Keywords and Connection are built out in this UI kit.",
    action: /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      onClick: () => setScreen('dashboard')
    }, "Back to Statistics")
  })));
  return /*#__PURE__*/React.createElement(window.WpChrome, {
    screen: screen,
    onNavigate: setScreen
  }, view);
}
ReactDOM.createRoot(document.getElementById('root')).render(/*#__PURE__*/React.createElement(ClientApp, null));
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/wp_client/ClientApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/wp_client/ConnectionScreen.jsx
try { (() => {
function ConnectionScreen({
  connected,
  onConnect
}) {
  const {
    Card,
    Button,
    FormField,
    LicenseKeyField,
    StatusPill,
    FeatureList,
    Alert
  } = window.FyndableDesignSystem_229a7a;
  const [key, setKey] = React.useState('');
  return /*#__PURE__*/React.createElement(window.PluginPage, {
    title: "Connection",
    description: "Link this site to your Fyndable dashboard to unlock AI features."
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: '600px',
      margin: '0 auto'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    glass: true,
    padding: "60px",
    style: {
      textAlign: 'center'
    }
  }, connected ? /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(StatusPill, {
    tone: "success"
  }, "License active"), /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 'var(--text-2xl)',
      fontWeight: 'var(--weight-bold)',
      margin: '20px 0'
    }
  }, "You're connected to ", /*#__PURE__*/React.createElement("span", {
    style: {
      background: 'var(--fyn-gradient)',
      WebkitBackgroundClip: 'text',
      WebkitTextFillColor: 'transparent',
      backgroundClip: 'text'
    }
  }, "Fyndable")), /*#__PURE__*/React.createElement("p", {
    style: {
      color: 'var(--text-muted)',
      fontSize: 'var(--text-sm)',
      marginBottom: '30px'
    }
  }, "All Business-tier features are available on this site."), /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'left',
      marginTop: '40px',
      paddingTop: '30px',
      borderTop: '2px solid var(--border-subtle)'
    }
  }, /*#__PURE__*/React.createElement(LicenseKeyField, {
    label: "License key",
    value: "FYN-8H2K-4LM9-QW3R-7T5Y"
  }), /*#__PURE__*/React.createElement(LicenseKeyField, {
    label: "Tenant key",
    value: "tnt_9f31c8ab4e"
  }), /*#__PURE__*/React.createElement(LicenseKeyField, {
    label: "Dashboard URL",
    value: "https://saas.fyndable.com"
  }), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 'var(--text-2xs)',
      color: 'var(--text-muted)',
      textTransform: 'uppercase',
      letterSpacing: 'var(--tracking-wide)',
      fontWeight: 'var(--weight-semibold)',
      margin: '20px 0 8px'
    }
  }, "Included in Business"), /*#__PURE__*/React.createElement(FeatureList, {
    items: [{
      label: 'AI Content Writer'
    }, {
      label: 'Content Optimizer'
    }, {
      label: 'Rank Tracker'
    }, {
      label: 'Search Console integration'
    }, {
      label: 'White-label options',
      locked: true
    }, {
      label: 'Unlimited API calls',
      locked: true
    }]
  }))) : /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(StatusPill, {
    tone: "danger"
  }, "Not connected"), /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 'var(--text-2xl)',
      fontWeight: 'var(--weight-bold)',
      margin: '20px 0'
    }
  }, "Connect to ", /*#__PURE__*/React.createElement("span", {
    style: {
      background: 'var(--fyn-gradient)',
      WebkitBackgroundClip: 'text',
      WebkitTextFillColor: 'transparent',
      backgroundClip: 'text'
    }
  }, "Fyndable")), /*#__PURE__*/React.createElement("p", {
    style: {
      color: 'var(--text-muted)',
      fontSize: 'var(--text-sm)',
      marginBottom: '30px'
    }
  }, "Enter the licence key from your Fyndable account to activate this site."), /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'left',
      marginTop: '30px'
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Dashboard URL",
    defaultValue: "https://saas.fyndable.com",
    description: "Include https://"
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "License key",
    placeholder: "FYN-XXXX-XXXX-XXXX-XXXX",
    value: key,
    onChange: e => setKey(e.target.value)
  }), /*#__PURE__*/React.createElement(Button, {
    size: "lg",
    style: {
      width: '100%'
    },
    onClick: onConnect
  }, "Activate license"), /*#__PURE__*/React.createElement(Alert, {
    tone: "info",
    style: {
      marginTop: '25px',
      marginBottom: 0
    }
  }, "No key yet? Start a 14-day trial from the Fyndable dashboard."))))));
}
Object.assign(window, {
  ConnectionScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/wp_client/ConnectionScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/wp_client/KeywordsScreen.jsx
try { (() => {
const keywordSeed = [['smart seo wordpress', 2400, 'medium', 'commercial', 4.1, 'Smart SEO'], ['ai meta description generator', 1300, 'low', 'transactional', 6.8, 'AI content'], ['seo plugin white label', 880, 'high', 'commercial', 11.5, 'White label'], ['topic cluster tool', 590, 'low', 'informational', 9.2, 'Clusters'], ['interne links automatiseren', 430, 'low', 'informational', 7.7, null], ['schema markup generator', 3600, 'high', 'transactional', 18.4, 'Schema']];
function KeywordsScreen() {
  const {
    Card,
    Button,
    Badge,
    IconButton,
    DataTable,
    Tabs,
    Pagination,
    FormField,
    Modal
  } = window.FyndableDesignSystem_229a7a;
  const [tab, setTab] = React.useState('all');
  const [adding, setAdding] = React.useState(false);
  const [list, setList] = React.useState(keywordSeed);
  const [draft, setDraft] = React.useState('');
  const add = () => {
    if (draft.trim()) setList([[draft.trim(), 0, 'low', 'informational', 0, null]].concat(list));
    setDraft('');
    setAdding(false);
  };
  const visible = tab === 'clustered' ? list.filter(k => k[5]) : tab === 'unclustered' ? list.filter(k => !k[5]) : list;
  const diffTone = {
    low: 'success',
    medium: 'warning',
    high: 'danger'
  };
  const intentTone = {
    commercial: 'accent',
    transactional: 'success',
    informational: 'info'
  };
  const rows = visible.map(k => ({
    kw: /*#__PURE__*/React.createElement("strong", {
      style: {
        fontWeight: 'var(--weight-semibold)'
      }
    }, k[0]),
    vol: k[1] ? k[1].toLocaleString('en-US') : '—',
    diff: /*#__PURE__*/React.createElement(Badge, {
      tone: diffTone[k[2]],
      uppercase: true
    }, k[2]),
    intent: /*#__PURE__*/React.createElement(Badge, {
      tone: intentTone[k[3]]
    }, k[3]),
    pos: k[4] || '—',
    cluster: k[5] ? /*#__PURE__*/React.createElement(Badge, {
      tone: "neutral"
    }, k[5]) : /*#__PURE__*/React.createElement("button", {
      type: "button",
      style: {
        padding: '4px 12px',
        border: '1px dashed var(--border-strong)',
        background: 'var(--fyn-white)',
        borderRadius: 'var(--radius-pill)',
        fontSize: 'var(--text-2xs)',
        cursor: 'pointer',
        color: 'var(--text-muted)',
        fontFamily: 'var(--font-core)'
      }
    }, "+ Set cluster"),
    act: /*#__PURE__*/React.createElement("span", {
      style: {
        display: 'flex',
        gap: '5px'
      }
    }, /*#__PURE__*/React.createElement(IconButton, {
      icon: "edit",
      label: "Edit",
      size: "sm"
    }), /*#__PURE__*/React.createElement(IconButton, {
      icon: "update",
      label: "Refresh",
      size: "sm",
      tone: "brand"
    }), /*#__PURE__*/React.createElement(IconButton, {
      icon: "chart-line",
      label: "Track",
      size: "sm"
    }), /*#__PURE__*/React.createElement(IconButton, {
      icon: "trash",
      label: "Delete",
      size: "sm",
      tone: "danger"
    }))
  }));
  return /*#__PURE__*/React.createElement(window.PluginPage, {
    title: "Keywords",
    description: "Every keyword you track, with volume, difficulty and intent."
  }, /*#__PURE__*/React.createElement(Card, {
    glass: true,
    padding: "30px"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'flex-start',
      gap: '12px',
      marginBottom: '25px',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    placeholder: "Search keywords\u2026",
    style: {
      marginBottom: 0,
      width: '280px'
    }
  }), /*#__PURE__*/React.createElement(Button, {
    icon: "plus",
    onClick: () => setAdding(true)
  }, "Add keyword"), /*#__PURE__*/React.createElement(Button, {
    variant: "secondary",
    icon: "update"
  }, "Refresh data")), /*#__PURE__*/React.createElement(Tabs, {
    value: tab,
    onChange: setTab,
    items: [{
      id: 'all',
      label: 'All',
      count: list.length
    }, {
      id: 'clustered',
      label: 'Clustered',
      count: list.filter(k => k[5]).length
    }, {
      id: 'unclustered',
      label: 'Unclustered',
      count: list.filter(k => !k[5]).length
    }]
  }), /*#__PURE__*/React.createElement(DataTable, {
    compact: true,
    columns: [{
      key: 'kw',
      label: 'Keyword'
    }, {
      key: 'vol',
      label: 'Volume',
      width: '90px',
      align: 'right'
    }, {
      key: 'diff',
      label: 'Difficulty',
      width: '110px'
    }, {
      key: 'intent',
      label: 'Intent',
      width: '130px'
    }, {
      key: 'pos',
      label: 'Pos',
      width: '60px',
      align: 'right'
    }, {
      key: 'cluster',
      label: 'Cluster',
      width: '150px'
    }, {
      key: 'act',
      label: 'Actions',
      width: '150px'
    }],
    rows: rows
  }), /*#__PURE__*/React.createElement(Pagination, {
    page: 1,
    pages: 4,
    info: 'Showing 1–' + rows.length + ' of 74 keywords'
  })), adding ? /*#__PURE__*/React.createElement(Modal, {
    title: "Add keyword",
    onClose: () => setAdding(false),
    footer: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      onClick: () => setAdding(false)
    }, "Cancel"), /*#__PURE__*/React.createElement(Button, {
      onClick: add
    }, "Add keyword"))
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Keyword",
    placeholder: "smart seo wordpress",
    value: draft,
    onChange: e => setDraft(e.target.value)
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Language",
    as: "select",
    options: ['Nederlands', 'English', 'Deutsch'],
    style: {
      marginBottom: 0
    }
  })) : null);
}
Object.assign(window, {
  KeywordsScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/wp_client/KeywordsScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/wp_client/SearchConsoleScreen.jsx
try { (() => {
const gscQueries = [['smart seo wordpress', 412, '18.2k', '2.3%', 4.1], ['ai meta description generator', 288, '12.9k', '2.2%', 6.8], ['seo plugin white label', 96, '7.4k', '1.3%', 11.5], ['topic cluster tool', 74, '5.1k', '1.4%', 9.2], ['interne links automatiseren', 61, '3.8k', '1.6%', 7.7]];
const gscPages = [['/features/smart-seo', 504, '21.6k', '2.3%', 5.2], ['/blog/semantische-seo', 322, '14.8k', '2.2%', 7.1], ['/pricing', 188, '9.2k', '2.0%', 4.4], ['/blog/interne-links', 97, '6.4k', '1.5%', 8.9], ['/integrations/gsc', 52, '2.9k', '1.8%', 12.3]];
function SearchConsoleScreen() {
  const {
    Card,
    Button,
    StatCard,
    DataTable,
    FormField
  } = window.FyndableDesignSystem_229a7a;
  const [days, setDays] = React.useState('28');
  const cols = first => [{
    key: 'a',
    label: first
  }, {
    key: 'b',
    label: 'Clicks',
    width: '60px',
    align: 'right'
  }, {
    key: 'c',
    label: 'Impr.',
    width: '70px',
    align: 'right'
  }, {
    key: 'd',
    label: 'CTR',
    width: '55px',
    align: 'right'
  }, {
    key: 'e',
    label: 'Pos',
    width: '45px',
    align: 'right'
  }];
  const toRows = src => src.map(r => ({
    a: r[0],
    b: r[1],
    c: r[2],
    d: r[3],
    e: r[4]
  }));
  return /*#__PURE__*/React.createElement(window.PluginPage, {
    title: "Google Search Console",
    description: "Performance data from Google Search Console. Connect via Fyndable \u2192 Settings to enable."
  }, /*#__PURE__*/React.createElement(Card, {
    glass: true,
    padding: "30px"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: '10px',
      alignItems: 'flex-start',
      marginBottom: '20px'
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    as: "select",
    value: days,
    onChange: e => setDays(e.target.value),
    style: {
      marginBottom: 0,
      width: '200px'
    },
    options: [{
      value: '7',
      label: 'Last 7 days'
    }, {
      value: '28',
      label: 'Last 28 days'
    }, {
      value: '90',
      label: 'Last 3 months'
    }]
  }), /*#__PURE__*/React.createElement(Button, null, "Load data")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(4,1fr)',
      gap: '15px',
      marginBottom: '20px'
    }
  }, /*#__PURE__*/React.createElement(StatCard, {
    value: days === '7' ? '298' : '1.2k',
    label: "Clicks",
    tone: "brand",
    change: "+8.2%"
  }), /*#__PURE__*/React.createElement(StatCard, {
    value: days === '7' ? '9.4k' : '38.4k',
    label: "Impressions",
    tone: "info",
    change: "+12.1%"
  }), /*#__PURE__*/React.createElement(StatCard, {
    value: "3.1%",
    label: "Avg CTR",
    tone: "success"
  }), /*#__PURE__*/React.createElement(StatCard, {
    value: "14.2",
    label: "Avg position",
    tone: "warning",
    change: "-1.4",
    changeTone: "negative"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      gap: '20px'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "Top Queries",
    icon: "search",
    padding: "0",
    style: {
      boxShadow: 'var(--shadow-sm)'
    }
  }, /*#__PURE__*/React.createElement(DataTable, {
    compact: true,
    columns: cols('Query'),
    rows: toRows(gscQueries)
  })), /*#__PURE__*/React.createElement(Card, {
    title: "Top Pages",
    icon: "admin-page",
    padding: "0",
    style: {
      boxShadow: 'var(--shadow-sm)'
    }
  }, /*#__PURE__*/React.createElement(DataTable, {
    compact: true,
    columns: cols('Page'),
    rows: toRows(gscPages)
  })))));
}
Object.assign(window, {
  SearchConsoleScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/wp_client/SearchConsoleScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/wp_client/StatisticsScreen.jsx
try { (() => {
function StatisticsScreen() {
  const {
    Card,
    Button,
    ScoreRing,
    ProgressBar,
    AnalysisList,
    Spinner
  } = window.FyndableDesignSystem_229a7a;
  const [state, setState] = React.useState('idle');
  const run = () => {
    setState('loading');
    window.setTimeout(() => setState('done'), 900);
  };
  return /*#__PURE__*/React.createElement(window.PluginPage, {
    title: "Statistics",
    description: "Site-wide SEO health, issues and quick wins."
  }, /*#__PURE__*/React.createElement(Card, {
    glass: true,
    padding: "30px"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: '15px',
      marginBottom: '25px'
    }
  }, /*#__PURE__*/React.createElement(Button, {
    onClick: run,
    icon: "update"
  }, "Analyze site"), state === 'loading' ? /*#__PURE__*/React.createElement(Spinner, {
    label: "Scanning 118 posts\u2026"
  }) : null), state === 'done' ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gap: '20px'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '280px 1fr',
      gap: '20px'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    padding: "30px",
    style: {
      boxShadow: 'var(--shadow-sm)'
    }
  }, /*#__PURE__*/React.createElement(ScoreRing, {
    score: 72,
    label: "Needs Work",
    caption: "118 posts analyzed"
  })), /*#__PURE__*/React.createElement(Card, {
    title: "SEO Breakdown",
    icon: "chart-bar",
    style: {
      boxShadow: 'var(--shadow-sm)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gap: '12px'
    }
  }, /*#__PURE__*/React.createElement(ProgressBar, {
    label: "SEO Titles",
    hint: "112/118 (95%)",
    value: 95,
    tone: "success"
  }), /*#__PURE__*/React.createElement(ProgressBar, {
    label: "Meta Descriptions",
    hint: "76/118 (64%)",
    value: 64,
    tone: "warning"
  }), /*#__PURE__*/React.createElement(ProgressBar, {
    label: "Focus Keyphrases",
    hint: "41/118 (35%)",
    value: 35,
    tone: "danger"
  }), /*#__PURE__*/React.createElement(ProgressBar, {
    label: "Open Graph Tags",
    hint: "98/118 (83%)",
    value: 83,
    tone: "success"
  }), /*#__PURE__*/React.createElement(ProgressBar, {
    label: "Internal Links",
    hint: "88/118 (75%)",
    value: 75
  }), /*#__PURE__*/React.createElement(ProgressBar, {
    label: "Content Length",
    hint: "104/118 (88%)",
    value: 88,
    tone: "success"
  })))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      gap: '20px'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "Issues Found",
    icon: "warning",
    style: {
      boxShadow: 'var(--shadow-sm)'
    }
  }, /*#__PURE__*/React.createElement(AnalysisList, {
    items: [{
      tone: 'error',
      label: '42 posts missing meta description',
      meta: '42'
    }, {
      tone: 'error',
      label: '6 posts missing SEO title',
      meta: '6'
    }, {
      tone: 'warning',
      label: '77 posts missing focus keyphrase',
      meta: '77'
    }, {
      tone: 'warning',
      label: '20 posts missing Open Graph tags',
      meta: '20'
    }, {
      tone: 'info',
      label: '30 posts have no internal links',
      meta: '30'
    }, {
      tone: 'info',
      label: '14 posts have thin content (<300 words)',
      meta: '14'
    }]
  })), /*#__PURE__*/React.createElement(Card, {
    title: "Quick Wins",
    icon: "lightbulb",
    style: {
      boxShadow: 'var(--shadow-sm)'
    }
  }, /*#__PURE__*/React.createElement("p", {
    style: {
      color: 'var(--text-muted)',
      fontSize: 'var(--text-xs)',
      marginTop: 0
    }
  }, "Posts with good content but missing SEO meta \u2014 easy to fix!"), [['Hoe werkt semantische SEO in 2026?', 'Good content but missing SEO meta — easy to fix with AI'], ['Checklist: technische SEO voor WooCommerce', 'Good content but missing SEO meta — easy to fix with AI'], ['Interne links automatiseren zonder plugin-chaos', 'Good content but missing SEO meta — easy to fix with AI']].map(([t, r]) => /*#__PURE__*/React.createElement("div", {
    key: t,
    style: {
      padding: '6px 0',
      borderBottom: '1px solid var(--border-subtle)'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      fontSize: 'var(--text-ui)'
    }
  }, t), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-2xs)',
      color: 'var(--text-subtle)'
    }
  }, r)))))) : /*#__PURE__*/React.createElement("p", {
    style: {
      color: 'var(--text-muted)',
      margin: 0
    }
  }, "Run an analysis to see your site's SEO score, breakdown and quick wins.")));
}
Object.assign(window, {
  StatisticsScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/wp_client/StatisticsScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/wp_client/WpChrome.jsx
try { (() => {
const wpMenu = [{
  id: 'connection',
  label: '🔗 Connection'
}, {
  id: 'dashboard',
  label: '📊 Dashboard'
}, {
  id: 'calendar',
  label: '📅 Content Calendar'
}, {
  id: 'tools',
  label: '🤖 AI Tools'
}, {
  id: 'ideas',
  label: '💡 Ideas'
}, {
  id: 'posts',
  label: '📝 Created Posts'
}, {
  id: 'keywords',
  label: '🎯 Keywords'
}, {
  id: 'links',
  label: '🔗 Link Manager'
}, {
  id: 'sitemaps',
  label: '🗺️ Sitemaps'
}, {
  id: 'integrations',
  label: '🔌 Integrations'
}, {
  id: 'seodata',
  label: '📈 SEO Data'
}, {
  id: 'llm',
  label: '🧠 LLM Tracker'
}, {
  id: 'clusters',
  label: '🎯 Topic Clusters'
}, {
  id: 'audit',
  label: '🔍 Site Audit'
}, {
  id: 'rank',
  label: '📈 Rank Tracker'
}, {
  id: 'gsc',
  label: '📊 Search Console'
}, {
  id: 'google',
  label: '📈 Google Data'
}, {
  id: 'abtest',
  label: '🧪 A/B Testing'
}, {
  id: 'settings',
  label: '⚙️ Settings'
}];

/* WordPress admin chrome. These greys/blues are WordPress core's own colours,
   not Fyndable's — the plugin lives inside them. */
function WpChrome({
  screen,
  onNavigate,
  children
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      minHeight: '100vh',
      background: '#f0f0f1',
      fontFamily: 'var(--font-core)'
    }
  }, /*#__PURE__*/React.createElement("nav", {
    style: {
      width: '190px',
      flexShrink: 0,
      background: '#1d2327',
      color: '#f0f0f1',
      paddingBottom: '40px'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: '8px',
      padding: '12px 12px 14px',
      borderBottom: '1px solid #2c3338'
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "dashicons dashicons-wordpress",
    style: {
      color: '#f0f0f1'
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '13px',
      opacity: .7
    }
  }, "fyndable.com")), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '8px 12px',
      display: 'flex',
      alignItems: 'center',
      gap: '8px',
      color: '#a7aaad',
      fontSize: '14px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "dashicons dashicons-dashboard"
  }), " Dashboard"), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '8px 12px',
      display: 'flex',
      alignItems: 'center',
      gap: '8px',
      color: '#a7aaad',
      fontSize: '14px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "dashicons dashicons-admin-post"
  }), " Posts"), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: '6px',
      background: '#2c3338'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '10px 12px',
      display: 'flex',
      alignItems: 'center',
      gap: '8px',
      fontSize: '14px',
      fontWeight: 600,
      background: '#1d2327'
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "dashicons dashicons-chart-line",
    style: {
      color: '#fff'
    }
  }), " Fyndable"), wpMenu.map(m => {
    const on = m.id === screen;
    return /*#__PURE__*/React.createElement("button", {
      key: m.id,
      type: "button",
      onClick: () => onNavigate(m.id),
      style: {
        display: 'block',
        width: '100%',
        textAlign: 'left',
        padding: '7px 12px 7px 24px',
        border: 'none',
        cursor: 'pointer',
        fontFamily: 'var(--font-core)',
        fontSize: '13px',
        background: on ? '#2271b1' : 'transparent',
        color: on ? '#fff' : '#c3c4c7'
      }
    }, m.label);
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '12px',
      marginTop: '6px',
      display: 'flex',
      alignItems: 'center',
      gap: '8px',
      color: '#a7aaad',
      fontSize: '14px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "dashicons dashicons-admin-settings"
  }), " Settings")), /*#__PURE__*/React.createElement("main", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, children));
}

/* The plugin's own page shell: dark header band + brand-gradient content field. */
function PluginPage({
  title,
  description,
  actions,
  children
}) {
  const {
    PageHeader
  } = window.FyndableDesignSystem_229a7a;
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHeader, {
    title: title,
    description: description,
    actions: actions
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '40px',
      background: 'var(--fyn-gradient)',
      minHeight: 'calc(100vh - 118px)'
    }
  }, children));
}
Object.assign(window, {
  WpChrome,
  PluginPage,
  wpMenu
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/wp_client/WpChrome.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.IconButton = __ds_scope.IconButton;

__ds_ns.Spinner = __ds_scope.Spinner;

__ds_ns.StatusPill = __ds_scope.StatusPill;

__ds_ns.Tooltip = __ds_scope.Tooltip;

__ds_ns.AnalysisList = __ds_scope.AnalysisList;

__ds_ns.DataTable = __ds_scope.DataTable;

__ds_ns.FeatureList = __ds_scope.FeatureList;

__ds_ns.Pagination = __ds_scope.Pagination;

__ds_ns.ProgressBar = __ds_scope.ProgressBar;

__ds_ns.ScoreRing = __ds_scope.ScoreRing;

__ds_ns.SerpPreview = __ds_scope.SerpPreview;

__ds_ns.StatCard = __ds_scope.StatCard;

__ds_ns.Alert = __ds_scope.Alert;

__ds_ns.Modal = __ds_scope.Modal;

__ds_ns.FormField = __ds_scope.FormField;

__ds_ns.LicenseKeyField = __ds_scope.LicenseKeyField;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.EmptyState = __ds_scope.EmptyState;

__ds_ns.NavPills = __ds_scope.NavPills;

__ds_ns.PageHeader = __ds_scope.PageHeader;

__ds_ns.QuickAction = __ds_scope.QuickAction;

__ds_ns.Tabs = __ds_scope.Tabs;

__ds_ns.ToolCard = __ds_scope.ToolCard;

})();
