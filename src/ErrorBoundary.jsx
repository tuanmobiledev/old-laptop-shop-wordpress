import React from 'react';

export default class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    if (typeof window !== 'undefined' && window.gtag) {
      window.gtag('event', 'exception', {
        description: `${error.name}: ${error.message}`,
        fatal: true,
      });
    }
    console.error('ErrorBoundary caught:', error, info);
  }

  handleReload = () => {
    this.setState({ error: null });
    window.location.reload();
  };

  render() {
    if (this.state.error) {
      return (
        <div className="error-boundary" style={{
          display: 'flex', flexDirection: 'column', alignItems: 'center',
          justifyContent: 'center', minHeight: '60vh', padding: '2rem',
          textAlign: 'center', fontFamily: 'Inter, sans-serif', color: '#0B0B0B',
        }}>
          <h1 style={{ fontSize: '1.5rem', marginBottom: '0.5rem' }}>Đã xảy ra lỗi</h1>
          <p style={{ color: '#6D6D6D', marginBottom: '1.5rem', maxWidth: '400px' }}>
            Có lỗi không mong muốn. Thử tải lại trang hoặc liên hệ hỗ trợ nếu vẫn gặp lỗi.
          </p>
          <button onClick={this.handleReload} style={{
            padding: '0.75rem 2rem', borderRadius: '12px', border: 'none',
            background: '#0B0B0B', color: '#fff', cursor: 'pointer',
            fontSize: '1rem', fontWeight: 600,
          }}>
            Tải lại trang
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}
