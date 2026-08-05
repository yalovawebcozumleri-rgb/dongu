import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as SplashScreen from 'expo-splash-screen';
import AuthGate from './src/auth/AuthGate';
import { AuthProvider } from './src/auth/AuthProvider';
import { NoticeProvider } from './src/notice/NoticeProvider';
import { SafeAreaProvider } from 'react-native-safe-area-context';

SplashScreen.preventAutoHideAsync().catch(() => {});
SplashScreen.setOptions({ duration: 250, fade: true });

type BoundaryState = {
  error: Error | null;
  revision: number;
};

class AppErrorBoundary extends React.Component<React.PropsWithChildren, BoundaryState> {
  state: BoundaryState = { error: null, revision: 0 };

  static getDerivedStateFromError(error: Error): Partial<BoundaryState> {
    return { error };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo) {
    console.error('Döngü ekran hatası', error, info.componentStack);
  }

  retry = () => {
    this.setState(current => ({ error: null, revision: current.revision + 1 }));
  };

  render() {
    if (this.state.error) {
      return (
        <View style={styles.errorScreen}>
          <View style={styles.errorIcon}><Text style={styles.errorIconText}>!</Text></View>
          <Text style={styles.errorTitle}>Ekran yüklenemedi</Text>
          <Text style={styles.errorText}>{this.state.error.message}</Text>
          <Pressable onPress={this.retry} style={styles.retryButton}>
            <Text style={styles.retryText}>Tekrar dene</Text>
          </Pressable>
        </View>
      );
    }

    return <React.Fragment key={this.state.revision}>{this.props.children}</React.Fragment>;
  }
}

export default function App() {
  return (
    <AppErrorBoundary>
      <SafeAreaProvider>
        <NoticeProvider>
          <AuthProvider>
            <AuthGate />
          </AuthProvider>
        </NoticeProvider>
      </SafeAreaProvider>
    </AppErrorBoundary>
  );
}

const styles = StyleSheet.create({
  errorScreen: { flex: 1, padding: 28, backgroundColor: '#F4F7F2', alignItems: 'center', justifyContent: 'center' },
  errorIcon: { width: 58, height: 58, borderRadius: 29, backgroundColor: '#FFF1EC', alignItems: 'center', justifyContent: 'center', marginBottom: 14 },
  errorIconText: { color: '#9C3D27', fontSize: 28, fontWeight: '900' },
  errorTitle: { color: '#16231C', fontSize: 20, fontWeight: '900' },
  errorText: { color: '#718077', fontSize: 11, lineHeight: 17, textAlign: 'center', marginTop: 7 },
  retryButton: { height: 48, paddingHorizontal: 24, borderRadius: 15, backgroundColor: '#176B45', alignItems: 'center', justifyContent: 'center', marginTop: 18 },
  retryText: { color: '#FFFFFF', fontSize: 12, fontWeight: '900' },
});
