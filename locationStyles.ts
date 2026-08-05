import { StyleSheet } from 'react-native';
import { C } from './styles';
import { ls as base } from './locationBase';

export const ls = {
  ...base,
  ...StyleSheet.create({
    permissionWarning: { borderRadius: 15, padding: 13, backgroundColor: '#FFF1EC', borderWidth: 1, borderColor: '#F2C9BB', marginBottom: 20 },
    permissionWarningTitle: { color: '#9C3D27', fontSize: 12, fontWeight: '900' },
    permissionWarningText: { color: '#8D594B', fontSize: 12, lineHeight: 18, marginTop: 4 },
    settingsButton: { alignSelf: 'flex-start', marginTop: 10, height: 36, paddingHorizontal: 13, borderRadius: 11, backgroundColor: '#9C3D27', alignItems: 'center', justifyContent: 'center' },
    settingsButtonText: { color: C.white, fontSize: 12, fontWeight: '900' },
  }),
};
