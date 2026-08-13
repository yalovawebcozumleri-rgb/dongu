import React, { memo } from 'react';
import { ActivityIndicator, GestureResponderEvent, Pressable, Text, View } from 'react-native';
import Svg, { Ellipse, G, Line, Path, Rect } from 'react-native-svg';
import { ds } from '../../detailStyles';
import {
  Coordinates,
  distanceKm,
  distanceLabel,
  Listing,
  listingCount,
  listingPrice,
  materialColor,
  Material,
  money,
} from '../../marketplace';
import { C, s } from '../../styles';
import UserAvatar from '../profile/UserAvatar';

export function MaterialIcon({ material }: { material: Material }) {
  return (
    <View accessible={false} style={[s.smallMaterialIcon, { backgroundColor: materialColor[material] }]}>
      <Svg width={22} height={28} viewBox="0 0 24 32">
        <G fill="none" stroke={C.dark} strokeWidth={1.7} strokeLinecap="round" strokeLinejoin="round">
          {material === 'PET' ? (
            <>
              <Path d="M9 2h6v4.5l2.1 3.1c.6.9.9 1.9.9 3V27a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V12.6c0-1.1.3-2.1.9-3L9 6.5V2Z" />
              <Line x1="9" y1="5" x2="15" y2="5" />
              <Rect x="8" y="15" width="8" height="7" rx="1.2" strokeOpacity={0.58} />
            </>
          ) : material === 'Cam' ? (
            <>
              <Path d="M9 2h6v8.1c0 .9.3 1.7 1 2.3l1 1c.7.7 1 1.5 1 2.5V27a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V15.9c0-1 .3-1.8 1-2.5l1-1c.7-.6 1-1.4 1-2.3V2Z" />
              <Line x1="9" y1="6" x2="15" y2="6" />
              <Line x1="9" y1="16" x2="9" y2="25" strokeOpacity={0.5} />
            </>
          ) : (
            <>
              <Path d="M7 5.5v21c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5v-21" />
              <Ellipse cx="12" cy="5.5" rx="5" ry="2.5" />
              <Path d="M10.1 5.3c.8-.7 2.7-.7 3.8 0l-1.3 1.2h-2.5V5.3Z" />
              <Path d="M7 25.5c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5" />
              <Line x1="9" y1="11" x2="9" y2="22" strokeOpacity={0.45} />
            </>
          )}
        </G>
      </Svg>
    </View>
  );
}

function ListingCard({
  item,
  center,
  open,
  isOwn,
  toggleFavorite,
  favoritePending = false,
}: {
  item: Listing;
  center: Coordinates | null;
  open: () => void;
  isOwn: boolean;
  toggleFavorite?: () => void;
  favoritePending?: boolean;
}) {
  const count = listingCount(item);
  const total = listingPrice(item);
  const sellerStatus = item.ratingCount > 0 && item.rating !== null
    ? `★ ${item.rating.toFixed(1).replace('.', ',')} · ${item.ratingCount} oy · ${item.sellerTransactions} teslimat`
    : item.sellerTransactions > 0
      ? `${item.sellerTransactions} teslimat · Henüz değerlendirilmedi`
      : 'Yeni kullanıcı · Henüz teslimatı yok';

  const onFavoritePress = (event: GestureResponderEvent) => {
    event.stopPropagation();
    toggleFavorite?.();
  };

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${count} adet ${item.items.map(line => line.material).join(', ')}. ${item.district}. Toplam ${money(total)}. Satıcı ${item.seller}. ${sellerStatus}`}
      accessibilityHint="İlan ayrıntılarını açar"
      onPress={open}
      style={({ pressed }) => [s.card, pressed && s.pressed]}
    >
      <View style={s.cardTop}>
        <View style={[s.materialStack, { width: 42 + Math.min(item.items.length - 1, 2) * 16 }]}>
          {item.items.slice(0, 3).map((line, index) => (
            <View key={line.material} style={[s.stackedIcon, { left: index * 16, zIndex: 3 - index }]}>
              <MaterialIcon material={line.material} />
            </View>
          ))}
        </View>
        <View style={s.cardTitleArea}>
          <View style={s.titleLine}>
            <Text style={s.countText}>{count} adet</Text>
            <View style={s.tinyDot} />
            <Text style={s.timeText}>{item.time}</Text>
          </View>
          <Text style={s.materialText}>{item.items.map(line => line.material).join(' · ')}</Text>
          {item.isBoosted && !isOwn && <View style={s.boostedBadgeInline}><Text style={s.boostedBadgeText}>ÖNE ÇIKAN</Text></View>}
        </View>
        <View style={s.cardActions}>
          {isOwn ? (
            <>
              <View style={s.ownListingBadge}><Text style={s.ownListingBadgeText}>SENİN İLANIN</Text></View>
              {item.isBoosted && <View style={s.boostedBadge}><Text style={s.boostedBadgeText}>ÖNE ÇIKAN</Text></View>}
            </>
          ) : (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={item.isFavorited ? 'Favorilerden kaldır' : 'Favorilere ekle'}
              disabled={favoritePending}
              accessibilityState={{ selected: item.isFavorited, disabled: favoritePending }}
              onPress={onFavoritePress}
              hitSlop={6}
              style={[s.favoriteButton, item.isFavorited && s.favoriteButtonActive]}
            >
              {favoritePending
                ? <ActivityIndicator size="small" color={C.green} />
                : (
                  <Svg width={19} height={19} viewBox="0 0 24 24" accessible={false}>
                    <Path
                      d="M12 21S4.2 16.47 2.42 12.17C.97 8.67 2.93 5 6.66 5c2.18 0 3.7 1.18 4.54 2.42C12.04 6.18 13.56 5 15.74 5c3.73 0 5.69 3.67 4.24 7.17C18.2 16.47 12 21 12 21Z"
                      fill={item.isFavorited ? '#C84F3D' : 'none'}
                      stroke={item.isFavorited ? '#C84F3D' : C.muted}
                      strokeWidth={1.9}
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </Svg>
                )}
            </Pressable>
          )}
          {!!center && (
            <View style={s.distancePill}>
              <Text style={s.distanceText}>⌖ {distanceLabel(item.distanceKm ?? distanceKm(center, item))}</Text>
            </View>
          )}
        </View>
      </View>
      {item.requestStatus === 'pending' && (
        <View style={ds.pendingBanner}>
          <Text style={ds.pendingIcon}>◷</Text>
          <View style={ds.pendingCopy}>
            <Text style={ds.pendingTitle}>ALIM TALEBİN GÖNDERİLDİ</Text>
            <Text style={ds.pendingText}>Satıcının yanıtı bekleniyor</Text>
          </View>
        </View>
      )}
      <View style={s.breakdown}>
        {item.items.map(line => (
          <View key={line.material} style={s.breakdownChip}>
            <Text style={s.breakdownName}>{line.material}</Text>
            <Text style={s.breakdownValue}>{line.count} × {line.unitPrice.toFixed(2).replace('.', ',')} TL</Text>
          </View>
        ))}
      </View>
      <View style={s.locationRow}><Text style={s.locationText}>●  {item.district}</Text></View>
      <View style={s.moneyRow}>
        <View>
          <Text style={s.moneyLabel}>Toplam satış fiyatı</Text>
          <Text style={s.moneyValue}>{money(total)}</Text>
        </View>
        <View style={s.gainBox}>
          <Text style={s.gainLabel}>Potansiyel brüt fark</Text>
          <Text style={s.gainValue}>+{money(count - total)}</Text>
        </View>
      </View>
      <View style={s.sellerRow}>
        <UserAvatar uri={item.sellerAvatarUrl} name={item.seller} size={34} />
        <View style={s.sellerCopy}>
          <Text style={s.sellerName}>{item.seller}</Text>
          <Text style={s.sellerStatus}>{sellerStatus}</Text>
        </View>
        <Text style={s.detailLink}>İncele  ›</Text>
      </View>
    </Pressable>
  );
}

export default memo(ListingCard, (previous, next) =>
  previous.item === next.item
  && previous.center === next.center
  && previous.isOwn === next.isOwn
  && previous.favoritePending === next.favoritePending
);
